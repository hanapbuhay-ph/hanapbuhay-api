<?php

use App\Models\Booking;
use App\Models\RatingReview;
use App\Models\ServiceCategory;
use App\Models\WorkerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Local helpers (closures — no global function collisions) ──────────────────

$makeCompletedBooking = function (?int $clientId = null, ?int $workerId = null): Booking {
    $client   = $clientId ? \App\Models\User::find($clientId) : makeBookingClient();
    $worker   = $workerId ? \App\Models\User::find($workerId) : makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    return Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => 'completed',
    ]);
};

// ── Happy path ────────────────────────────────────────────────────────────────

it('client rates a completed booking and worker stats are updated', function () use ($makeCompletedBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeCompletedBooking($client->id, $worker->id);

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/rate", [
            'score'   => 5,
            'comment' => 'Very professional and on time!',
        ])
        ->assertStatus(201)
        ->assertJsonStructure(['success', 'message', 'data' => ['rating' => ['id', 'score', 'comment']]])
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Review submitted.')
        ->assertJsonPath('data.rating.score', 5)
        ->assertJsonPath('data.rating.comment', 'Very professional and on time!');

    $this->assertDatabaseHas('ratings_reviews', [
        'booking_id' => $booking->id,
        'rated_by'   => $client->id,
        'rated_user' => $worker->id,
        'score'      => 5,
    ]);

    $profile = WorkerProfile::where('user_id', $worker->id)->first();
    expect((float) $profile->average_rating)->toBe(5.0)
        ->and($profile->total_reviews)->toBe(1);
});

it('worker rates a completed booking and no worker stats update occurs', function () use ($makeCompletedBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeCompletedBooking($client->id, $worker->id);

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/rate", ['score' => 4])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('ratings_reviews', [
        'booking_id' => $booking->id,
        'rated_by'   => $worker->id,
        'rated_user' => $client->id,
    ]);

    // client has no worker_profile — assert no profile row was touched
    $this->assertDatabaseMissing('worker_profiles', ['user_id' => $client->id]);
});

// ── Status guard ──────────────────────────────────────────────────────────────

it('cannot rate a pending booking', function () use ($makeCompletedBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeCompletedBooking($client->id, $worker->id);
    $booking->update(['status' => 'pending']);

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/rate", ['score' => 5])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'You can only rate a completed booking.');
});

it('cannot rate a cancelled booking', function () use ($makeCompletedBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeCompletedBooking($client->id, $worker->id);
    $booking->update(['status' => 'cancelled']);

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/rate", ['score' => 3])
        ->assertStatus(422)
        ->assertJsonPath('message', 'You can only rate a completed booking.');
});

// ── Duplicate guard ───────────────────────────────────────────────────────────

it('same user cannot rate the same booking twice', function () use ($makeCompletedBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeCompletedBooking($client->id, $worker->id);

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/rate", ['score' => 5])
        ->assertStatus(201);

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/rate", ['score' => 3])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'You have already rated this booking.');
});

// ── Authorization ─────────────────────────────────────────────────────────────

it('unrelated user cannot rate a booking they are not part of', function () use ($makeCompletedBooking) {
    $booking   = $makeCompletedBooking();
    $outsider  = makeBookingClient();

    $this->actingAs($outsider)
        ->postJson("/api/bookings/{$booking->id}/rate", ['score' => 5])
        ->assertStatus(403);
});

it('unauthenticated request returns 401', function () use ($makeCompletedBooking) {
    $booking = $makeCompletedBooking();

    $this->postJson("/api/bookings/{$booking->id}/rate", ['score' => 5])
        ->assertStatus(401);
});

// ── Validation ────────────────────────────────────────────────────────────────

it('returns 422 when score is missing', function () use ($makeCompletedBooking) {
    $client  = makeBookingClient();
    $booking = $makeCompletedBooking($client->id);

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/rate", [])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['score']]);
});

it('returns 422 when score is 0', function () use ($makeCompletedBooking) {
    $client  = makeBookingClient();
    $booking = $makeCompletedBooking($client->id);

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/rate", ['score' => 0])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['score']]);
});

it('returns 422 when score is 6', function () use ($makeCompletedBooking) {
    $client  = makeBookingClient();
    $booking = $makeCompletedBooking($client->id);

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/rate", ['score' => 6])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['score']]);
});

it('returns 422 when comment exceeds 1000 characters', function () use ($makeCompletedBooking) {
    $client  = makeBookingClient();
    $booking = $makeCompletedBooking($client->id);

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/rate", [
            'score'   => 5,
            'comment' => str_repeat('a', 1001),
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['comment']]);
});

it('accepts a rating without a comment', function () use ($makeCompletedBooking) {
    $client  = makeBookingClient();
    $booking = $makeCompletedBooking($client->id);

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/rate", ['score' => 4])
        ->assertStatus(201)
        ->assertJsonPath('data.rating.comment', null);
});

// ── Worker stats recalculation ────────────────────────────────────────────────

it('recalculates average_rating and total_reviews after two clients rate the same worker', function () use ($makeCompletedBooking) {
    $worker   = makeApprovedBookingWorker();
    $client1  = makeBookingClient();
    $client2  = makeBookingClient();
    $category = ServiceCategory::factory()->create();

    $booking1 = Booking::factory()->create([
        'client_id'           => $client1->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => 'completed',
    ]);

    $booking2 = Booking::factory()->create([
        'client_id'           => $client2->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => 'completed',
    ]);

    $this->actingAs($client1)
        ->postJson("/api/bookings/{$booking1->id}/rate", ['score' => 4])
        ->assertStatus(201);

    $this->actingAs($client2)
        ->postJson("/api/bookings/{$booking2->id}/rate", ['score' => 2])
        ->assertStatus(201);

    $profile = WorkerProfile::where('user_id', $worker->id)->first();
    expect((float) $profile->average_rating)->toBe(3.0)
        ->and($profile->total_reviews)->toBe(2);
});

// ── rated_user resolution ─────────────────────────────────────────────────────

it('sets rated_by = client and rated_user = worker user_id when client rates', function () use ($makeCompletedBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeCompletedBooking($client->id, $worker->id);

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/rate", ['score' => 5])
        ->assertStatus(201);

    $rating = RatingReview::where('booking_id', $booking->id)->first();
    expect($rating->rated_by)->toBe($client->id)
        ->and($rating->rated_user)->toBe($worker->id);
});

it('sets rated_by = worker and rated_user = client_id when worker rates', function () use ($makeCompletedBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeCompletedBooking($client->id, $worker->id);

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/rate", ['score' => 3])
        ->assertStatus(201);

    $rating = RatingReview::where('booking_id', $booking->id)->first();
    expect($rating->rated_by)->toBe($worker->id)
        ->and($rating->rated_user)->toBe($client->id);
});

// ── 404 ───────────────────────────────────────────────────────────────────────

it('returns 404 for a non-existent booking', function () {
    $client = makeBookingClient();

    $this->actingAs($client)
        ->postJson('/api/bookings/99999/rate', ['score' => 5])
        ->assertStatus(404);
});
