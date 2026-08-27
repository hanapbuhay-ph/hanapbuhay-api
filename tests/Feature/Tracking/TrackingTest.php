<?php

use App\Events\LocationTrackingStarted;
use App\Events\LocationTrackingStopped;
use App\Events\LocationUpdated;
use App\Events\NewMessage;
use App\Models\Barangay;
use App\Models\Booking;
use App\Models\BookingTracking;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

// ── Shared setup closure ──────────────────────────────────────────────────────

$makeActiveBooking = function (?User $client = null, ?User $worker = null): Booking {
    $client   ??= makeBookingClient();
    $worker   ??= makeApprovedBookingWorker();
    $category   = ServiceCategory::factory()->create();

    return Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => 'active',
    ]);
};

// ── Tracking Start ────────────────────────────────────────────────────────────

it('worker starts tracking on an active booking', function () use ($makeActiveBooking) {
    Event::fake();

    $worker  = makeApprovedBookingWorker();
    $booking = $makeActiveBooking(worker: $worker);

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/tracking/start")
        ->assertStatus(200)
        ->assertJsonPath('data.role', 'worker')
        ->assertJsonPath('data.is_worker_tracking', true);

    expect(Booking::find($booking->id)->is_worker_tracking)->toBeTrue();

    Event::assertDispatched(LocationTrackingStarted::class, function ($e) use ($booking) {
        return $e->bookingId === $booking->id && $e->role === 'worker';
    });
});

it('client starts tracking on an active booking', function () use ($makeActiveBooking) {
    Event::fake();

    $client  = makeBookingClient();
    $booking = $makeActiveBooking(client: $client);

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/tracking/start")
        ->assertStatus(200)
        ->assertJsonPath('data.role', 'client')
        ->assertJsonPath('data.is_client_tracking', true);

    expect(Booking::find($booking->id)->is_client_tracking)->toBeTrue();

    Event::assertDispatched(LocationTrackingStarted::class, function ($e) use ($booking) {
        return $e->bookingId === $booking->id && $e->role === 'client';
    });
});

it('returns 422 when starting tracking on a pending booking', function () use ($makeActiveBooking) {
    $worker  = makeApprovedBookingWorker();
    $booking = $makeActiveBooking(worker: $worker);
    $booking->update(['status' => 'pending']);

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/tracking/start")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Tracking is only available for active bookings.');
});

it('returns 403 when unrelated user tries to start tracking', function () use ($makeActiveBooking) {
    $booking = $makeActiveBooking();
    $other   = makeBookingClient();

    $this->actingAs($other)
        ->postJson("/api/bookings/{$booking->id}/tracking/start")
        ->assertStatus(403);
});

it('returns 401 when unauthenticated on tracking start', function () use ($makeActiveBooking) {
    $booking = $makeActiveBooking();

    $this->postJson("/api/bookings/{$booking->id}/tracking/start")
        ->assertStatus(401);
});

// ── Tracking Update ───────────────────────────────────────────────────────────

it('worker sends a location update', function () use ($makeActiveBooking) {
    Event::fake();

    $worker  = makeApprovedBookingWorker();
    $booking = $makeActiveBooking(worker: $worker);
    $booking->update(['is_worker_tracking' => true]);

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/tracking/update", [
            'latitude'  => 10.1234567,
            'longitude' => 124.1234567,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.role', 'worker')
        ->assertJsonPath('data.latitude', 10.1234567)
        ->assertJsonPath('data.longitude', 124.1234567);

    expect(BookingTracking::where('booking_id', $booking->id)->count())->toBe(1);

    Event::assertDispatched(LocationUpdated::class, function ($e) use ($booking) {
        return $e->bookingId === $booking->id
            && $e->role === 'worker'
            && $e->latitude === 10.1234567
            && $e->longitude === 124.1234567;
    });
});

it('returns 422 when updating location without starting tracking first', function () use ($makeActiveBooking) {
    $worker  = makeApprovedBookingWorker();
    $booking = $makeActiveBooking(worker: $worker);

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/tracking/update", [
            'latitude'  => 10.1234567,
            'longitude' => 124.1234567,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'You must start tracking before sending updates.');
});

it('returns 422 for invalid latitude greater than 90', function () use ($makeActiveBooking) {
    $worker  = makeApprovedBookingWorker();
    $booking = $makeActiveBooking(worker: $worker);

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/tracking/update", [
            'latitude'  => 91.0,
            'longitude' => 124.0,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['latitude']);
});

it('returns 422 for invalid longitude greater than 180', function () use ($makeActiveBooking) {
    $worker  = makeApprovedBookingWorker();
    $booking = $makeActiveBooking(worker: $worker);

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/tracking/update", [
            'latitude'  => 10.0,
            'longitude' => 181.0,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['longitude']);
});

it('returns 403 when unrelated user tries to send location update', function () use ($makeActiveBooking) {
    $booking = $makeActiveBooking();
    $other   = makeBookingClient();

    $this->actingAs($other)
        ->postJson("/api/bookings/{$booking->id}/tracking/update", [
            'latitude'  => 10.0,
            'longitude' => 124.0,
        ])
        ->assertStatus(403);
});

// ── Tracking Stop ─────────────────────────────────────────────────────────────

it('worker stops tracking', function () use ($makeActiveBooking) {
    Event::fake();

    $worker  = makeApprovedBookingWorker();
    $booking = $makeActiveBooking(worker: $worker);
    $booking->update(['is_worker_tracking' => true]);

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/tracking/stop")
        ->assertStatus(200)
        ->assertJsonPath('data.is_worker_tracking', false);

    expect(Booking::find($booking->id)->is_worker_tracking)->toBeFalse();

    Event::assertDispatched(LocationTrackingStopped::class, function ($e) use ($booking) {
        return $e->bookingId === $booking->id && $e->role === 'worker';
    });
});

it('stop tracking is allowed even when booking is completed', function () {
    Event::fake();

    $worker   = makeApprovedBookingWorker();
    $client   = makeBookingClient();
    $category = ServiceCategory::factory()->create();

    $booking = Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => 'completed',
        'is_worker_tracking'  => true,
    ]);

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/tracking/stop")
        ->assertStatus(200)
        ->assertJsonPath('data.is_worker_tracking', false);

    Event::assertDispatched(LocationTrackingStopped::class);
});

it('returns 403 when unrelated user tries to stop tracking', function () use ($makeActiveBooking) {
    $booking = $makeActiveBooking();
    $other   = makeBookingClient();

    $this->actingAs($other)
        ->postJson("/api/bookings/{$booking->id}/tracking/stop")
        ->assertStatus(403);
});

// ── Tracking Show ─────────────────────────────────────────────────────────────

it('returns tracking state and both barangay coordinates', function () {
    $clientBarangay = Barangay::factory()->create([
        'name'      => 'Calanggaman',
        'latitude'  => 10.1234567,
        'longitude' => 124.1234567,
    ]);
    $workerBarangay = Barangay::factory()->create([
        'name'      => 'Trinidad',
        'latitude'  => 10.9876543,
        'longitude' => 124.9876543,
    ]);

    $client   = makeBookingClient();
    $client->update(['barangay_id' => $clientBarangay->id]);

    $worker   = makeApprovedBookingWorker();
    $worker->update(['barangay_id' => $workerBarangay->id]);

    $category = ServiceCategory::factory()->create();
    $booking  = Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => 'active',
        'is_worker_tracking'  => true,
    ]);

    $this->actingAs($client)
        ->getJson("/api/bookings/{$booking->id}/tracking")
        ->assertStatus(200)
        ->assertJsonPath('data.is_worker_tracking', true)
        ->assertJsonPath('data.client_barangay.name', 'Calanggaman')
        ->assertJsonPath('data.worker_barangay.name', 'Trinidad');
});

it('returns 403 when unrelated user tries to view tracking state', function () {
    $category = ServiceCategory::factory()->create();
    $booking  = Booking::factory()->create([
        'service_category_id' => $category->id,
        'status'              => 'active',
    ]);
    $other = makeBookingClient();

    $this->actingAs($other)
        ->getJson("/api/bookings/{$booking->id}/tracking")
        ->assertStatus(403);
});

it('returns 404 for non-existent booking on tracking show', function () {
    $user = makeBookingClient();

    $this->actingAs($user)
        ->getJson('/api/bookings/99999/tracking')
        ->assertStatus(404);
});

// ── Message Broadcast ─────────────────────────────────────────────────────────

it('sending a message dispatches NewMessage event with correct payload', function () {
    Event::fake();

    $client   = makeBookingClient();
    $worker   = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    $booking = Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => 'active',
    ]);

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/messages", [
            'message' => 'Hello worker!',
        ])
        ->assertStatus(201);

    Event::assertDispatched(NewMessage::class, function ($e) use ($booking, $client) {
        return $e->bookingId === $booking->id
            && $e->senderId === $client->id
            && $e->senderName === $client->name
            && $e->message === 'Hello worker!';
    });
});
