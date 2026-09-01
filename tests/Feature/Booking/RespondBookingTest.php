<?php

use App\Models\Booking;
use App\Models\ServiceCategory;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── helper ────────────────────────────────────────────────────────────────────

function makeRespondBooking(string $status = 'pending'): Booking
{
    $client   = makeBookingClient();
    $worker   = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    return Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => $status,
    ]);
}

// ══════════════════════════════════════════════════════════════════════════════
// POST /api/bookings/{id}/respond  (unified spec endpoint)
// ══════════════════════════════════════════════════════════════════════════════

it('worker can accept a booking via respond endpoint', function () {
    $booking = makeRespondBooking('pending');
    $worker  = $booking->worker;

    $this->mock(NotificationService::class)
        ->shouldReceive('notify')->once()
        ->shouldReceive('sendPush')->once();

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/respond", ['action' => 'accept'])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Booking accepted.')
        ->assertJsonPath('data.booking.status', 'accepted');

    expect($booking->fresh()->status)->toBe('accepted');
});

it('worker can decline a booking via respond endpoint', function () {
    $booking = makeRespondBooking('pending');
    $worker  = $booking->worker;

    $this->mock(NotificationService::class)
        ->shouldReceive('notify')->once()
        ->shouldReceive('sendPush')->once();

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/respond", [
            'action' => 'decline',
            'reason' => 'Schedule conflict.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Booking declined.')
        ->assertJsonPath('data.booking.status', 'declined');

    expect($booking->fresh()->status)->toBe('declined');
});

it('returns 422 when action is missing', function () {
    $booking = makeRespondBooking('pending');
    $worker  = $booking->worker;

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/respond", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['action']);
});

it('returns 422 when action is invalid', function () {
    $booking = makeRespondBooking('pending');
    $worker  = $booking->worker;

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/respond", ['action' => 'ignore'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['action']);
});

it('returns 422 when trying to respond to an already-accepted booking', function () {
    $booking = makeRespondBooking('accepted');
    $worker  = $booking->worker;

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/respond", ['action' => 'accept'])
        ->assertStatus(422);
});

it('returns 403 when a different worker tries to respond', function () {
    $booking       = makeRespondBooking('pending');
    $otherWorker   = makeApprovedBookingWorker();

    $this->actingAs($otherWorker)
        ->postJson("/api/bookings/{$booking->id}/respond", ['action' => 'accept'])
        ->assertStatus(403);
});

it('returns 403 when a client tries to use respond endpoint', function () {
    $booking = makeRespondBooking('pending');
    $client  = makeBookingClient();

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/respond", ['action' => 'accept'])
        ->assertStatus(403);
});

it('returns 404 for a non-existent booking', function () {
    $worker = makeApprovedBookingWorker();

    $this->actingAs($worker)
        ->postJson('/api/bookings/99999/respond', ['action' => 'accept'])
        ->assertStatus(404);
});

it('returns 401 when unauthenticated', function () {
    $this->postJson('/api/bookings/1/respond', ['action' => 'accept'])
        ->assertStatus(401);
});
