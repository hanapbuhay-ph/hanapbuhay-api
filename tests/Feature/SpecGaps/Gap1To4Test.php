<?php

use App\Models\Booking;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeGapBooking(string $status = 'pending'): Booking
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

function makeGapAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'is_active' => true]);
}

// ══════════════════════════════════════════════════════════════════════════════
// GAP 1 — POST /api/bookings/{id}/status  (spec §F6)
// ══════════════════════════════════════════════════════════════════════════════

it('worker can mark a booking as active via /status endpoint', function () {
    $booking = makeGapBooking('accepted');
    $worker  = $booking->worker;

    $this->mock(NotificationService::class)
        ->shouldReceive('notify')->zeroOrMoreTimes()
        ->shouldReceive('sendPush')->zeroOrMoreTimes();

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/status", ['status' => 'active'])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Booking marked as active.')
        ->assertJsonPath('data.booking.status', 'active');

    expect($booking->fresh()->status)->toBe('active');
});

it('worker can mark a booking as completed via /status endpoint', function () {
    $booking = makeGapBooking('active');
    $worker  = $booking->worker;

    $this->mock(NotificationService::class)
        ->shouldReceive('notify')->once()
        ->shouldReceive('sendPush')->once();

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/status", ['status' => 'completed'])
        ->assertStatus(200)
        ->assertJsonPath('data.booking.status', 'completed');

    expect($booking->fresh()->status)->toBe('completed');
});

it('returns 422 when status value is invalid on /status endpoint', function () {
    $booking = makeGapBooking('accepted');
    $worker  = $booking->worker;

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/status", ['status' => 'cancelled'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('returns 403 when client tries to use /status endpoint', function () {
    $booking = makeGapBooking('accepted');
    $client  = $booking->client;

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/status", ['status' => 'active'])
        ->assertStatus(403);
});

it('returns 401 when unauthenticated on /status endpoint', function () {
    $this->postJson('/api/bookings/1/status', ['status' => 'active'])->assertStatus(401);
});

// ══════════════════════════════════════════════════════════════════════════════
// GAP 2 — POST /api/ratings  (spec §H1, booking_id in body)
// ══════════════════════════════════════════════════════════════════════════════

it('client can submit a rating via POST /api/ratings', function () {
    $booking = makeGapBooking('completed');
    $client  = $booking->client;

    $this->mock(NotificationService::class)
        ->shouldReceive('notify')->once()
        ->shouldReceive('sendPush')->once();

    $this->actingAs($client)
        ->postJson('/api/ratings', [
            'booking_id' => $booking->id,
            'score'      => 5,
            'comment'    => 'Very professional!',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Rating submitted.')
        ->assertJsonStructure(['data' => ['rating' => ['id', 'score', 'comment']]]);
});

it('returns 422 when booking_id is missing on /api/ratings', function () {
    $client = makeBookingClient();

    $this->actingAs($client)
        ->postJson('/api/ratings', ['score' => 5])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['booking_id']);
});

it('returns 422 when rating a non-completed booking via /api/ratings', function () {
    $booking = makeGapBooking('pending');
    $client  = $booking->client;

    $this->actingAs($client)
        ->postJson('/api/ratings', [
            'booking_id' => $booking->id,
            'score'      => 4,
        ])
        ->assertStatus(422);
});

it('comment max:300 is enforced on /api/ratings', function () {
    $booking = makeGapBooking('completed');
    $client  = $booking->client;

    $this->actingAs($client)
        ->postJson('/api/ratings', [
            'booking_id' => $booking->id,
            'score'      => 5,
            'comment'    => str_repeat('a', 301),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['comment']);
});

it('returns 401 when unauthenticated on /api/ratings', function () {
    $this->postJson('/api/ratings', [])->assertStatus(401);
});

// ══════════════════════════════════════════════════════════════════════════════
// GAP 3 — POST /api/bookings/{id}/tracking/location  (spec §G2 alias)
// ══════════════════════════════════════════════════════════════════════════════

it('location alias endpoint works the same as /tracking/update', function () {
    $booking = makeGapBooking('active');
    $worker  = $booking->worker;
    $booking->update(['is_worker_tracking' => true]);

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/tracking/location", [
            'latitude'  => 9.9530,
            'longitude' => 124.3660,
            'accuracy'  => 5.2,
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Location updated.');
});

it('location alias returns 422 when tracking not started', function () {
    $booking = makeGapBooking('active');
    $worker  = $booking->worker;
    // is_worker_tracking is false by default

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/tracking/location", [
            'latitude'  => 9.9530,
            'longitude' => 124.3660,
        ])
        ->assertStatus(422);
});

it('returns 401 when unauthenticated on /tracking/location', function () {
    $this->postJson('/api/bookings/1/tracking/location', [])->assertStatus(401);
});

// ══════════════════════════════════════════════════════════════════════════════
// GAP 4 — POST /api/admin/users/{id}/toggle-status  (spec §K7)
// ══════════════════════════════════════════════════════════════════════════════

it('admin can suspend a user via toggle-status endpoint', function () {
    $admin  = makeGapAdmin();
    $client = makeBookingClient();

    $this->actingAs($admin)
        ->postJson("/api/admin/users/{$client->id}/toggle-status", [
            'action' => 'suspend',
            'reason' => 'Repeated policy violations.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'User account suspended.')
        ->assertJsonPath('data.is_active', false);

    expect($client->fresh()->is_active)->toBeFalse();
});

it('admin can reactivate a user via toggle-status endpoint', function () {
    $admin  = makeGapAdmin();
    $client = User::factory()->create(['role' => 'client', 'is_active' => false]);

    $this->actingAs($admin)
        ->postJson("/api/admin/users/{$client->id}/toggle-status", [
            'action' => 'reactivate',
        ])
        ->assertStatus(200)
        ->assertJsonPath('message', 'User account reactivated.')
        ->assertJsonPath('data.is_active', true);
});

it('toggle-status requires reason when action is suspend', function () {
    $admin  = makeGapAdmin();
    $client = makeBookingClient();

    $this->actingAs($admin)
        ->postJson("/api/admin/users/{$client->id}/toggle-status", [
            'action' => 'suspend',
            // no reason
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);
});

it('toggle-status writes an audit log entry', function () {
    $admin  = makeGapAdmin();
    $client = makeBookingClient();

    $this->actingAs($admin)
        ->postJson("/api/admin/users/{$client->id}/toggle-status", [
            'action' => 'suspend',
            'reason' => 'Policy violation.',
        ]);

    $this->assertDatabaseHas('admin_audit_logs', [
        'admin_id'    => $admin->id,
        'action'      => 'user_suspend',
        'target_type' => 'User',
        'target_id'   => $client->id,
    ]);
});

it('returns 422 when admin tries to toggle-status their own account', function () {
    $admin = makeGapAdmin();

    $this->actingAs($admin)
        ->postJson("/api/admin/users/{$admin->id}/toggle-status", [
            'action' => 'suspend',
            'reason' => 'Self-suspension test.',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 422 for invalid action on toggle-status', function () {
    $admin  = makeGapAdmin();
    $client = makeBookingClient();

    $this->actingAs($admin)
        ->postJson("/api/admin/users/{$client->id}/toggle-status", [
            'action' => 'delete',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['action']);
});

it('returns 403 for non-admin on toggle-status', function () {
    $client = makeBookingClient();
    $target = makeBookingClient();

    $this->actingAs($client)
        ->postJson("/api/admin/users/{$target->id}/toggle-status", [
            'action' => 'suspend',
            'reason' => 'test',
        ])
        ->assertStatus(403);
});

it('returns 401 when unauthenticated on toggle-status', function () {
    $this->postJson('/api/admin/users/1/toggle-status', [])->assertStatus(401);
});
