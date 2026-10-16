<?php

use App\Models\Barangay;
use App\Models\Booking;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function validPayload(int $workerId, int $categoryId): array
{
    return [
        'worker_id'           => $workerId,
        'service_category_id' => $categoryId,
        'scheduled_at'        => now()->addDays(3)->toDateTimeString(),
        'notes'               => 'Leaking pipe under kitchen sink',
    ];
}

it('allows a client to create a booking (happy path)', function () {
    Mail::fake();
    $client   = makeBookingClient();
    $worker   = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    $response = $this->actingAs($client)
        ->postJson('/api/bookings', validPayload($worker->id, $category->id));

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Booking created.')
        ->assertJsonStructure(['data' => ['booking' => ['id', 'booking_code', 'status']]]);

    $this->assertDatabaseHas('hanapbuhay_notifications', [
        'user_id' => $worker->id,
        'type' => 'booking_created',
    ]);
});

it('respects worker booking notification preferences when receiving a request', function () {
    Mail::fake();
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();
    $worker->update([
        'notify_booking_updates' => false,
        'notify_email' => false,
        'notify_push' => false,
    ]);
    $category = ServiceCategory::factory()->create();

    $this->actingAs($client)
        ->postJson('/api/bookings', validPayload($worker->id, $category->id))
        ->assertStatus(201);

    expect(\App\Models\HanapbuhayNotification::where('user_id', $worker->id)->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('generates booking_code in HB-YYYY-XXXXX format', function () {
    $client   = makeBookingClient();
    $worker   = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    $response = $this->actingAs($client)
        ->postJson('/api/bookings', validPayload($worker->id, $category->id));

    $code = $response->json('data.booking.booking_code');
    expect($code)->toMatch('/^HB-\d{4}-\d{5}$/');
});

it('returns 403 when a worker tries to create a booking', function () {
    $worker   = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    $this->actingAs($worker)
        ->postJson('/api/bookings', validPayload($worker->id, $category->id))
        ->assertStatus(403);
});

it('allows booking an unverified worker (warning is client-side only per spec)', function () {
    $client   = makeBookingClient();
    $category = ServiceCategory::factory()->create();
    $worker   = User::factory()->create(['role' => 'worker']);
    WorkerProfile::factory()->create([
        'user_id'             => $worker->id,
        'verification_status' => 'pending',
    ]);

    $this->actingAs($client)
        ->postJson('/api/bookings', validPayload($worker->id, $category->id))
        ->assertStatus(201)
        ->assertJsonPath('success', true);
});

it('rejects a second client booking the same worker at the exact same time', function () {
    Mail::fake();
    $firstClient = makeBookingClient();
    $secondClient = makeBookingClient();
    $worker = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();
    $scheduledAt = now()->addDays(3)->startOfMinute()->toDateTimeString();
    $payload = validPayload($worker->id, $category->id);
    $payload['scheduled_at'] = $scheduledAt;

    $this->actingAs($firstClient)
        ->postJson('/api/bookings', $payload)
        ->assertCreated();

    $this->actingAs($secondClient)
        ->postJson('/api/bookings', $payload)
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'This worker already has a booking scheduled at that time.');

    $this->assertDatabaseCount('bookings', 1);
});

it('releases a booking time after the worker declines the request', function () {
    Mail::fake();
    $firstClient = makeBookingClient();
    $secondClient = makeBookingClient();
    $worker = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();
    $scheduledAt = now()->addDays(3)->startOfMinute()->toDateTimeString();
    $payload = validPayload($worker->id, $category->id);
    $payload['scheduled_at'] = $scheduledAt;

    $bookingResponse = $this->actingAs($firstClient)
        ->postJson('/api/bookings', $payload)
        ->assertCreated();

    $this->actingAs($worker)
        ->postJson('/api/bookings/'.$bookingResponse->json('data.booking.id').'/decline')
        ->assertOk();

    $this->actingAs($secondClient)
        ->postJson('/api/bookings', $payload)
        ->assertCreated();

    $this->assertDatabaseCount('bookings', 2);
});

it('prevents rescheduling a booking into an occupied worker time', function () {
    Mail::fake();
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();
    $firstTime = now()->addDays(3)->startOfMinute();
    $occupiedTime = now()->addDays(4)->startOfMinute();
    $firstPayload = validPayload($worker->id, $category->id);
    $firstPayload['scheduled_at'] = $firstTime->toDateTimeString();
    $occupiedPayload = validPayload($worker->id, $category->id);
    $occupiedPayload['scheduled_at'] = $occupiedTime->toDateTimeString();

    $bookingResponse = $this->actingAs($client)
        ->postJson('/api/bookings', $firstPayload)
        ->assertCreated();
    $this->actingAs(makeBookingClient())
        ->postJson('/api/bookings', $occupiedPayload)
        ->assertCreated();

    $this->actingAs($client)
        ->postJson('/api/bookings/'.$bookingResponse->json('data.booking.id').'/reschedule', [
            'scheduled_at' => $occupiedTime->toDateTimeString(),
        ])
        ->assertStatus(409);
});

it('returns 422 when scheduled_at is in the past', function () {
    $client   = makeBookingClient();
    $worker   = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    $payload                 = validPayload($worker->id, $category->id);
    $payload['scheduled_at'] = now()->subDay()->toDateTimeString();

    $this->actingAs($client)
        ->postJson('/api/bookings', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['scheduled_at']);
});

it('returns 422 when client tries to book themselves', function () {
    $category = ServiceCategory::factory()->create();
    $client   = makeBookingClient();

    $this->actingAs($client)
        ->postJson('/api/bookings', validPayload($client->id, $category->id))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['worker_id']);
});

it('returns 422 when required fields are missing', function () {
    $client = makeBookingClient();

    $this->actingAs($client)
        ->postJson('/api/bookings', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['worker_id', 'service_category_id', 'scheduled_at']);
});

it('returns 401 when unauthenticated', function () {
    $this->postJson('/api/bookings', [])->assertStatus(401);
});
