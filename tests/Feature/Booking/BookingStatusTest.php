<?php

use App\Models\Booking;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bookingWithStatus(string $status, ?User $client = null, ?User $worker = null): Booking
{
    $client   ??= User::factory()->create(['role' => 'client']);
    $worker   ??= User::factory()->create(['role' => 'worker']);
    $category   = ServiceCategory::factory()->create();

    return Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => $status,
        'scheduled_at'        => now()->addDay(),
    ]);
}

function workerUser(): User
{
    $worker = User::factory()->create(['role' => 'worker']);
    WorkerProfile::factory()->create(['user_id' => $worker->id]);
    return $worker;
}

// ── Accept ────────────────────────────────────────────────────────────────────

it('worker accepts a pending booking', function () {
    $worker  = workerUser();
    $booking = bookingWithStatus('pending', worker: $worker);

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/accept")
        ->assertStatus(200)
        ->assertJsonPath('data.booking.status', 'accepted');
});

it('worker cannot accept an already-accepted booking', function () {
    $worker  = workerUser();
    $booking = bookingWithStatus('accepted', worker: $worker);

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/accept")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Booking cannot be accepted.');
});

// ── Decline ───────────────────────────────────────────────────────────────────

it('worker declines a pending booking', function () {
    $worker  = workerUser();
    $booking = bookingWithStatus('pending', worker: $worker);

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/decline")
        ->assertStatus(200)
        ->assertJsonPath('data.booking.status', 'declined');
});

// ── Cancel ────────────────────────────────────────────────────────────────────

it('client cancels a pending booking', function () {
    $client  = User::factory()->create(['role' => 'client']);
    $worker  = workerUser();
    $booking = bookingWithStatus('pending', $client, $worker);

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/cancel", ['reason' => 'Change of plans'])
        ->assertStatus(200)
        ->assertJsonPath('data.booking.status', 'cancelled')
        ->assertJsonPath('data.booking.cancelled_by', 'client');
});

it('worker cancels an accepted booking', function () {
    $client  = User::factory()->create(['role' => 'client']);
    $worker  = workerUser();
    $booking = bookingWithStatus('accepted', $client, $worker);

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/cancel", ['reason' => 'Emergency'])
        ->assertStatus(200)
        ->assertJsonPath('data.booking.status', 'cancelled')
        ->assertJsonPath('data.booking.cancelled_by', 'worker');
});

it('cannot cancel a completed booking', function () {
    $client  = User::factory()->create(['role' => 'client']);
    $worker  = workerUser();
    $booking = bookingWithStatus('completed', $client, $worker);

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/cancel", ['reason' => 'Too late'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Booking cannot be cancelled.');
});

// ── Start ─────────────────────────────────────────────────────────────────────

it('worker starts an accepted booking', function () {
    $worker  = workerUser();
    $booking = bookingWithStatus('accepted', worker: $worker);

    $response = $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/start");

    $response->assertStatus(200)
        ->assertJsonPath('data.booking.status', 'active');

    expect($response->json('data.booking.started_at'))->not->toBeNull();
});

// ── Complete ──────────────────────────────────────────────────────────────────

it('worker completes an active booking', function () {
    $worker  = workerUser();
    $booking = bookingWithStatus('active', worker: $worker);

    $response = $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/complete");

    $response->assertStatus(200)
        ->assertJsonPath('data.booking.status', 'completed')
        ->assertJsonPath('data.booking.is_client_tracking', false)
        ->assertJsonPath('data.booking.is_worker_tracking', false);

    expect($response->json('data.booking.completed_at'))->not->toBeNull();
});

// ── Authorization ─────────────────────────────────────────────────────────────

it('wrong worker cannot accept another workers booking', function () {
    $booking    = bookingWithStatus('pending');
    $otherWorker = workerUser();

    $this->actingAs($otherWorker)
        ->postJson("/api/bookings/{$booking->id}/accept")
        ->assertStatus(403);
});

it('returns 401 when unauthenticated on status change', function () {
    $booking = bookingWithStatus('pending');

    $this->postJson("/api/bookings/{$booking->id}/accept")->assertStatus(401);
});
