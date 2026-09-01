<?php

use App\Models\Booking;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createBookingFor(User $client, User $worker, string $status = 'pending'): Booking
{
    $category = ServiceCategory::factory()->create();
    return Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => $status,
        'scheduled_at'        => now()->addDay(),
    ]);
}

it('client sees only their own bookings', function () {
    $client      = User::factory()->create(['role' => 'client']);
    $otherClient = User::factory()->create(['role' => 'client']);
    $worker      = makeWorkerForListing();

    createBookingFor($client, $worker);
    createBookingFor($otherClient, $worker);

    $response = $this->actingAs($client)->getJson('/api/bookings');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.bookings');
});

it('worker sees only their own bookings', function () {
    $worker      = makeWorkerForListing();
    $otherWorker = makeWorkerForListing();
    $client      = User::factory()->create(['role' => 'client']);

    createBookingFor($client, $worker);
    createBookingFor($client, $otherWorker);

    $response = $this->actingAs($worker)->getJson('/api/bookings');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data.bookings');
});

it('admin sees all bookings', function () {
    $admin  = User::factory()->create(['role' => 'admin']);
    $client = User::factory()->create(['role' => 'client']);
    $worker = makeWorkerForListing();

    createBookingFor($client, $worker);
    createBookingFor($client, $worker);

    $response = $this->actingAs($admin)->getJson('/api/bookings');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data.bookings');
});

it('filters bookings by status', function () {
    $client = User::factory()->create(['role' => 'client']);
    $worker = makeWorkerForListing();

    createBookingFor($client, $worker, 'pending');
    createBookingFor($client, $worker, 'accepted');

    $response = $this->actingAs($client)->getJson('/api/bookings?status=pending');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data.bookings')
        ->assertJsonPath('data.bookings.0.status', 'pending');
});

it('returns pagination metadata', function () {
    $client = User::factory()->create(['role' => 'client']);
    $worker = makeWorkerForListing();

    createBookingFor($client, $worker);

    $response = $this->actingAs($client)->getJson('/api/bookings');

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => ['pagination' => [
            'current_page', 'per_page', 'total', 'last_page',
        ]]]);
});

it('returns 401 when unauthenticated on list', function () {
    $this->getJson('/api/bookings')->assertStatus(401);
});

it('returns a single booking for an authorized user', function () {
    $client  = User::factory()->create(['role' => 'client']);
    $worker  = makeWorkerForListing();
    $booking = createBookingFor($client, $worker);

    $this->actingAs($client)
        ->getJson("/api/bookings/{$booking->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.booking.id', $booking->id);
});

it('returns 403 when a different user tries to view a booking', function () {
    $client   = User::factory()->create(['role' => 'client']);
    $worker   = makeWorkerForListing();
    $booking  = createBookingFor($client, $worker);
    $intruder = User::factory()->create(['role' => 'client']);

    $this->actingAs($intruder)
        ->getJson("/api/bookings/{$booking->id}")
        ->assertStatus(403);
});

it('returns 404 for a non-existent booking', function () {
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($client)
        ->getJson('/api/bookings/99999')
        ->assertStatus(404);
});
