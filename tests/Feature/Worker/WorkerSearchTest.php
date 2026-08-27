<?php

use App\Models\Barangay;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerProfile;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeApprovedWorker(array $profileOverrides = [], array $userOverrides = []): WorkerProfile
{
    $barangay = Barangay::factory()->create();
    $user     = User::factory()->create(array_merge([
        'role'              => 'worker',
        'email_verified_at' => now(),
        'barangay_id'       => $barangay->id,
    ], $userOverrides));

    return WorkerProfile::factory()->create(array_merge([
        'user_id'             => $user->id,
        'verification_status' => 'approved',
        'availability_status' => 'available',
    ], $profileOverrides));
}

function makeClient(): User
{
    $barangay = Barangay::factory()->create();
    return User::factory()->create([
        'role'              => 'client',
        'email_verified_at' => now(),
        'barangay_id'       => $barangay->id,
    ]);
}

it('returns only approved workers', function () {
    $client = makeClient();
    makeApprovedWorker();

    $response = $this->actingAs($client)->get('/api/workers');

    $response->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonStructure(['data' => ['workers', 'total']]);

    expect($response->json('data.total'))->toBe(1);
});

it('does not return unverified workers', function () {
    $client = makeClient();

    $barangay = Barangay::factory()->create();
    $user     = User::factory()->create(['role' => 'worker', 'email_verified_at' => now(), 'barangay_id' => $barangay->id]);
    WorkerProfile::factory()->create(['user_id' => $user->id, 'verification_status' => 'pending']);

    makeApprovedWorker();

    $response = $this->actingAs($client)->get('/api/workers');

    expect($response->json('data.total'))->toBe(1);
    $workerStatuses = collect($response->json('data.workers'))->pluck('verification_status');
    expect($workerStatuses->every(fn ($s) => $s === 'approved'))->toBeTrue();
});

it('filters by barangay_id', function () {
    $client         = makeClient();
    $targetBarangay = Barangay::factory()->create();

    $targetUser = User::factory()->create([
        'role'              => 'worker',
        'email_verified_at' => now(),
        'barangay_id'       => $targetBarangay->id,
    ]);
    WorkerProfile::factory()->create(['user_id' => $targetUser->id, 'verification_status' => 'approved']);

    makeApprovedWorker(); // different barangay

    $response = $this->actingAs($client)->get("/api/workers?barangay_id={$targetBarangay->id}");

    expect($response->json('data.total'))->toBe(1);
    expect($response->json('data.workers.0.barangay_id'))->toBe($targetBarangay->id);
});

it('filters by category_id', function () {
    $client   = makeClient();
    $category = ServiceCategory::factory()->create();

    $profile = makeApprovedWorker();
    $profile->serviceCategories()->attach($category->id);

    makeApprovedWorker(); // no category

    $response = $this->actingAs($client)->get("/api/workers?category_id={$category->id}");

    expect($response->json('data.total'))->toBe(1);
    expect($response->json('data.workers.0.worker_profile_id'))->toBe($profile->id);
});

it('filters by min_rating', function () {
    $client = makeClient();

    makeApprovedWorker(['average_rating' => 4.5]);
    makeApprovedWorker(['average_rating' => 3.0]);

    $response = $this->actingAs($client)->get('/api/workers?min_rating=4.0');

    expect($response->json('data.total'))->toBe(1);
    expect((float) $response->json('data.workers.0.average_rating'))->toBeGreaterThanOrEqual(4.0);
});

it('searches workers by partial name match', function () {
    $client = makeClient();

    makeApprovedWorker([], ['name' => 'Pedro Alonzo']);
    makeApprovedWorker([], ['name' => 'Maria Santos']);

    $response = $this->actingAs($client)->get('/api/workers?search=Pedro');

    expect($response->json('data.total'))->toBe(1);
    expect($response->json('data.workers.0.name'))->toBe('Pedro Alonzo');
});

it('returns 401 for unauthenticated request', function () {
    $this->get('/api/workers')->assertStatus(401);
});
