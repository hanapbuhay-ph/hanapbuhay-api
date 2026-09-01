<?php

use App\Models\Barangay;
use App\Models\ServiceCategory;
use App\Models\WorkerProfile;
use App\Models\User;
use App\Models\JobPost;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ══════════════════════════════════════════════════════════════════════════════
// PING
// ══════════════════════════════════════════════════════════════════════════════

it('ping returns 200 with expected structure (no auth needed)', function () {
    $this->getJson('/api/ping')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'HanapBuhay API is running')
        ->assertJsonStructure(['data' => ['version', 'scope', 'environment']]);
});

it('ping returns scope as Trinidad, Bohol', function () {
    $this->getJson('/api/ping')
        ->assertJsonPath('data.scope', 'Trinidad, Bohol');
});

// ══════════════════════════════════════════════════════════════════════════════
// BARANGAYS
// ══════════════════════════════════════════════════════════════════════════════

it('barangays returns 200 with list (no auth needed)', function () {
    Barangay::factory()->count(3)->create(['is_active' => true]);

    $this->getJson('/api/barangays')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Barangays retrieved.')
        ->assertJsonStructure([
            'data' => [['id', 'name', 'latitude', 'longitude']],
        ]);
});

it('barangays only returns active barangays', function () {
    Barangay::factory()->create(['name' => 'Active One',   'is_active' => true]);
    Barangay::factory()->create(['name' => 'Inactive One', 'is_active' => false]);

    $response = $this->getJson('/api/barangays')->assertStatus(200);

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('Active One');
    expect($names)->not->toContain('Inactive One');
});

it('barangays list is sorted alphabetically by name', function () {
    Barangay::factory()->create(['name' => 'Poblacion', 'is_active' => true]);
    Barangay::factory()->create(['name' => 'Alicia',    'is_active' => true]);
    Barangay::factory()->create(['name' => 'Mabini',    'is_active' => true]);

    $response = $this->getJson('/api/barangays')->assertStatus(200);

    $names = collect($response->json('data'))->pluck('name')->values()->all();
    $sorted = collect($names)->sort()->values()->all();
    expect($names)->toBe($sorted);
});

it('barangays returns empty array when no barangays exist', function () {
    $response = $this->getJson('/api/barangays')->assertStatus(200);
    expect($response->json('data'))->toBeArray()->toBeEmpty();
});

// ══════════════════════════════════════════════════════════════════════════════
// SERVICE CATEGORIES
// ══════════════════════════════════════════════════════════════════════════════

it('service-categories returns 200 with list (no auth needed)', function () {
    ServiceCategory::factory()->count(3)->create(['is_active' => true]);

    $this->getJson('/api/service-categories')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Categories retrieved.')
        ->assertJsonStructure([
            'data' => [['id', 'name', 'icon']],
        ]);
});

it('service-categories only returns active categories', function () {
    ServiceCategory::factory()->create(['name' => 'Plumbing',   'is_active' => true]);
    ServiceCategory::factory()->create(['name' => 'Old Service', 'is_active' => false]);

    $response = $this->getJson('/api/service-categories')->assertStatus(200);

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('Plumbing');
    expect($names)->not->toContain('Old Service');
});

it('service-categories list is sorted alphabetically', function () {
    ServiceCategory::factory()->create(['name' => 'Welding',   'is_active' => true]);
    ServiceCategory::factory()->create(['name' => 'Carpentry', 'is_active' => true]);
    ServiceCategory::factory()->create(['name' => 'Tutoring',  'is_active' => true]);

    $response = $this->getJson('/api/service-categories')->assertStatus(200);

    $names = collect($response->json('data'))->pluck('name')->values()->all();
    $sorted = collect($names)->sort()->values()->all();
    expect($names)->toBe($sorted);
});

// ══════════════════════════════════════════════════════════════════════════════
// BROWSE WORKERS BY CATEGORY
// ══════════════════════════════════════════════════════════════════════════════

it('browse by category returns 200 with expected structure', function () {
    $client   = makeBookingClient();
    $category = ServiceCategory::factory()->create(['is_active' => true]);
    $worker   = makeApprovedBookingWorker();
    $profile  = $worker->workerProfile;

    // Link worker to category
    $profile->serviceCategories()->attach($category->id);

    // Give them a job post in that category
    JobPost::factory()->create([
        'worker_profile_id'   => $profile->id,
        'service_category_id' => $category->id,
        'is_active'           => true,
    ]);

    $this->actingAs($client)
        ->getJson("/api/categories/{$category->id}/workers")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'category' => ['id', 'name'],
                'workers'  => [
                    ['worker_profile_id', 'name', 'distance_km', 'trust_tier', 'job_post'],
                ],
                'total',
            ],
        ]);
});

it('browse by category returns 404 for non-existent category', function () {
    $client = makeBookingClient();

    $this->actingAs($client)
        ->getJson('/api/categories/99999/workers')
        ->assertStatus(404);
});

it('browse by category returns 404 for inactive category', function () {
    $client   = makeBookingClient();
    $category = ServiceCategory::factory()->create(['is_active' => false]);

    $this->actingAs($client)
        ->getJson("/api/categories/{$category->id}/workers")
        ->assertStatus(404);
});

it('browse by category returns 401 when unauthenticated', function () {
    $category = ServiceCategory::factory()->create();

    $this->getJson("/api/categories/{$category->id}/workers")
        ->assertStatus(401);
});

it('browse by category does not include workers not in that category', function () {
    $client    = makeBookingClient();
    $category  = ServiceCategory::factory()->create(['is_active' => true]);
    $category2 = ServiceCategory::factory()->create(['is_active' => true]);
    $worker    = makeApprovedBookingWorker();

    // Worker is only in category2, NOT category
    $worker->workerProfile->serviceCategories()->attach($category2->id);

    $response = $this->actingAs($client)
        ->getJson("/api/categories/{$category->id}/workers")
        ->assertStatus(200);

    expect($response->json('data.workers'))->toBeEmpty();
    expect($response->json('data.total'))->toBe(0);
});

it('browse by category filters by barangay_id when provided', function () {
    $clientBarangay = Barangay::factory()->create();
    $client = User::factory()->create(['role' => 'client', 'barangay_id' => $clientBarangay->id]);

    $barangay1 = Barangay::factory()->create();
    $barangay2 = Barangay::factory()->create();
    $category  = ServiceCategory::factory()->create(['is_active' => true]);

    // Worker in barangay1
    $worker1 = User::factory()->create(['role' => 'worker', 'barangay_id' => $barangay1->id]);
    $profile1 = WorkerProfile::factory()->create(['user_id' => $worker1->id, 'verification_status' => 'approved']);
    $profile1->serviceCategories()->attach($category->id);

    // Worker in barangay2
    $worker2 = User::factory()->create(['role' => 'worker', 'barangay_id' => $barangay2->id]);
    $profile2 = WorkerProfile::factory()->create(['user_id' => $worker2->id, 'verification_status' => 'approved']);
    $profile2->serviceCategories()->attach($category->id);

    $response = $this->actingAs($client)
        ->getJson("/api/categories/{$category->id}/workers?barangay_id={$barangay1->id}")
        ->assertStatus(200);

    $ids = collect($response->json('data.workers'))->pluck('worker_profile_id');
    expect($ids)->toContain($profile1->id);
    expect($ids)->not->toContain($profile2->id);
});
