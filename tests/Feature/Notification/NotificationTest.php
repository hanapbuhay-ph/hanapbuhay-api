<?php

use App\Models\Booking;
use App\Models\DeviceToken;
use App\Models\ServiceCategory;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;

uses(RefreshDatabase::class);

// ── Shared setup ──────────────────────────────────────────────────────────────

$makeBooking = function (string $status, ?int $clientId = null, ?int $workerId = null): Booking {
    $client   = $clientId ? \App\Models\User::find($clientId) : makeBookingClient();
    $worker   = $workerId ? \App\Models\User::find($workerId) : makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    return Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => $status,
    ]);
};

// ── Device registration ───────────────────────────────────────────────────────

it('registers a new device token', function () {
    $user = makeBookingClient();

    $this->actingAs($user)
        ->postJson('/api/notifications/register-device', [
            'fcm_token'   => 'token-abc-123',
            'device_type' => 'android',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Device registered successfully.');

    $this->assertDatabaseHas('device_tokens', [
        'user_id'   => $user->id,
        'fcm_token' => 'token-abc-123',
    ]);
});

it('registering the same token twice results in only one row', function () {
    $user = makeBookingClient();

    $payload = ['fcm_token' => 'token-abc-123', 'device_type' => 'android'];

    $this->actingAs($user)->postJson('/api/notifications/register-device', $payload)->assertStatus(200);
    $this->actingAs($user)->postJson('/api/notifications/register-device', $payload)->assertStatus(200);

    expect(DeviceToken::where('user_id', $user->id)->count())->toBe(1);
});

it('returns 422 when fcm_token is missing', function () {
    $user = makeBookingClient();

    $this->actingAs($user)
        ->postJson('/api/notifications/register-device', ['device_type' => 'android'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['fcm_token']);
});

it('returns 422 when device_type is invalid', function () {
    $user = makeBookingClient();

    $this->actingAs($user)
        ->postJson('/api/notifications/register-device', [
            'fcm_token'   => 'token-abc-123',
            'device_type' => 'smartwatch',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['device_type']);
});

it('returns 401 when unauthenticated on register-device', function () {
    $this->postJson('/api/notifications/register-device', [
        'fcm_token'   => 'token-abc-123',
        'device_type' => 'android',
    ])->assertStatus(401);
});

// ── Push notification hooks ───────────────────────────────────────────────────

it('worker accepting a booking notifies the client', function () use ($makeBooking) {
    $notifications = mock(NotificationService::class);
    $this->app->instance(NotificationService::class, $notifications);

    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking('pending', $client->id, $worker->id);

    $notifications->shouldReceive('notify')->once();
    $notifications->shouldReceive('sendPush')
        ->once()
        ->withArgs(fn ($user, $title) => $user->id === $client->id && $title === 'Booking Accepted');

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/accept")
        ->assertStatus(200);
});

it('worker declining a booking notifies the client', function () use ($makeBooking) {
    $notifications = mock(NotificationService::class);
    $this->app->instance(NotificationService::class, $notifications);

    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking('pending', $client->id, $worker->id);

    $notifications->shouldReceive('notify')->once();
    $notifications->shouldReceive('sendPush')
        ->once()
        ->withArgs(fn ($user, $title) => $user->id === $client->id && $title === 'Booking Declined');

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/decline")
        ->assertStatus(200);
});

it('worker completing a booking notifies the client', function () use ($makeBooking) {
    $notifications = mock(NotificationService::class);
    $this->app->instance(NotificationService::class, $notifications);

    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking('active', $client->id, $worker->id);

    $notifications->shouldReceive('notify')->once();
    $notifications->shouldReceive('sendPush')
        ->once()
        ->withArgs(fn ($user, $title) => $user->id === $client->id && $title === 'Booking Completed');

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/complete")
        ->assertStatus(200);
});

it('client rating a booking notifies the rated worker', function () use ($makeBooking) {
    $notifications = mock(NotificationService::class);
    $this->app->instance(NotificationService::class, $notifications);

    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking('completed', $client->id, $worker->id);

    $notifications->shouldReceive('notify')->once();
    $notifications->shouldReceive('sendPush')
        ->once()
        ->withArgs(fn ($user, $title) => $user->id === $worker->id && $title === 'New Review');

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/rate", ['score' => 5])
        ->assertStatus(201);
});

// ── NotificationService unit behavior ────────────────────────────────────────

it('sendPush does nothing when user has no registered tokens', function () {
    $messaging = mock(Messaging::class);
    $messaging->shouldNotReceive('send');

    $service = new NotificationService($messaging);
    $user    = makeBookingClient();

    $service->sendPush($user, 'Test', 'Body');
});

it('sendPush calls FCM send once for a user with one token', function () {
    $messaging = mock(Messaging::class);
    $messaging->shouldReceive('send')->once()->andReturn([]);

    $service = new NotificationService($messaging);
    $user    = makeBookingClient();

    DeviceToken::create([
        'user_id'     => $user->id,
        'fcm_token'   => 'valid-token-xyz',
        'device_type' => 'android',
    ]);

    $service->sendPush($user, 'Hello', 'World');
});

it('sendPush deletes invalid token on MessagingException and does not throw', function () {
    $exception = NotFound::becauseTokenNotFound('bad-token-xyz');

    $messaging = mock(Messaging::class);
    $messaging->shouldReceive('send')->once()->andThrow($exception);

    $service = new NotificationService($messaging);
    $user    = makeBookingClient();

    DeviceToken::create([
        'user_id'     => $user->id,
        'fcm_token'   => 'bad-token-xyz',
        'device_type' => 'ios',
    ]);

    $service->sendPush($user, 'Hello', 'World');

    expect(DeviceToken::where('user_id', $user->id)->count())->toBe(0);
});
