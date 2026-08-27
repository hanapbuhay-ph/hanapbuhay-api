<?php

use App\Models\Barangay;
use App\Models\Booking;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeApprovedWorker(): User
{
    $barangay = Barangay::factory()->create();
    $worker   = User::factory()->create(['role' => 'worker', 'barangay_id' => $barangay->id]);
    WorkerProfile::factory()->create([
        'user_id'             => $worker->id,
        'verification_status' => 'approved',
    ]);
    return $worker;
}

function makeClient(): User
{
    return User::factory()->create(['role' => 'client']);
}

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
    $client   = makeClient();
    $worker   = makeApprovedWorker();
    $category = ServiceCategory::factory()->create();

    $response = $this->actingAs($client)
        ->postJson('/api/bookings', validPayload($worker->id, $category->id));

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Booking created.')
        ->assertJsonStructure(['data' => ['booking' => ['id', 'booking_code', 'status']]]);
});

it('generates booking_code in HB-YYYY-XXXXX format', function () {
    $client   = makeClient();
    $worker   = makeApprovedWorker();
    $category = ServiceCategory::factory()->create();

    $response = $this->actingAs($client)
        ->postJson('/api/bookings', validPayload($worker->id, $category->id));

    $code = $response->json('data.booking.booking_code');
    expect($code)->toMatch('/^HB-\d{4}-\d{5}$/');
});

it('returns 403 when a worker tries to create a booking', function () {
    $worker   = makeApprovedWorker();
    $category = ServiceCategory::factory()->create();

    $this->actingAs($worker)
        ->postJson('/api/bookings', validPayload($worker->id, $category->id))
        ->assertStatus(403);
});

it('returns 422 when the worker is not verified', function () {
    $client   = makeClient();
    $category = ServiceCategory::factory()->create();
    $worker   = User::factory()->create(['role' => 'worker']);
    WorkerProfile::factory()->create([
        'user_id'             => $worker->id,
        'verification_status' => 'pending',
    ]);

    $this->actingAs($client)
        ->postJson('/api/bookings', validPayload($worker->id, $category->id))
        ->assertStatus(422)
        ->assertJsonPath('message', 'This worker is not yet verified.');
});

it('returns 422 when scheduled_at is in the past', function () {
    $client   = makeClient();
    $worker   = makeApprovedWorker();
    $category = ServiceCategory::factory()->create();

    $payload              = validPayload($worker->id, $category->id);
    $payload['scheduled_at'] = now()->subDay()->toDateTimeString();

    $this->actingAs($client)
        ->postJson('/api/bookings', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['scheduled_at']);
});

it('returns 422 when client tries to book themselves', function () {
    $category = ServiceCategory::factory()->create();
    $client   = makeClient();

    $this->actingAs($client)
        ->postJson('/api/bookings', validPayload($client->id, $category->id))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['worker_id']);
});

it('returns 422 when required fields are missing', function () {
    $client = makeClient();

    $this->actingAs($client)
        ->postJson('/api/bookings', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['worker_id', 'service_category_id', 'scheduled_at']);
});

it('returns 401 when unauthenticated', function () {
    $this->postJson('/api/bookings', [])->assertStatus(401);
});
