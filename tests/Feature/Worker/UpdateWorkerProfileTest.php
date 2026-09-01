<?php

use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

function makeWorkerWithProfile(): User
{
    $user = User::factory()->create(['role' => 'worker', 'email_verified_at' => now()]);
    WorkerProfile::factory()->create(['user_id' => $user->id]);
    return $user;
}

it('updates bio and availability_status successfully', function () {
    $worker = makeWorkerWithProfile();

    $response = $this->actingAs($worker)->post('/api/worker/profile', [
        'bio'                 => 'Experienced electrician.',
        'availability_status' => 'available',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Profile updated.',
            'data'    => [
                'worker_profile' => [
                    'bio'                 => 'Experienced electrician.',
                    'availability_status' => 'available',
                ],
            ],
        ]);
});

it('syncs category_ids and verifies pivot table is updated', function () {
    $worker = makeWorkerWithProfile();
    $cat1   = ServiceCategory::factory()->create(['name' => 'Electrical Works']);
    $cat2   = ServiceCategory::factory()->create(['name' => 'Plumbing']);

    $response = $this->actingAs($worker)->post('/api/worker/profile', [
        'category_ids' => [$cat1->id, $cat2->id],
    ]);

    $response->assertStatus(200);

    $profileId = $worker->workerProfile->id;
    $this->assertDatabaseHas('worker_service_categories', [
        'worker_profile_id'   => $profileId,
        'service_category_id' => $cat1->id,
    ]);
    $this->assertDatabaseHas('worker_service_categories', [
        'worker_profile_id'   => $profileId,
        'service_category_id' => $cat2->id,
    ]);

    $categories = $response->json('data.worker_profile.categories');
    expect(collect($categories)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$cat1->id, $cat2->id])->sort()->values()->all());
});

it('returns 403 for non-worker (client role)', function () {
    $client = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);

    $this->actingAs($client)->post('/api/worker/profile', ['bio' => 'test'])
        ->assertStatus(403)
        ->assertJson(['success' => false, 'message' => 'This action is for workers only.']);
});

it('returns 422 for invalid availability_status value', function () {
    $worker = makeWorkerWithProfile();

    $this->actingAs($worker)->post('/api/worker/profile', ['availability_status' => 'on_vacation'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['availability_status']);
});

it('returns 422 when category_ids contains a non-existent ID', function () {
    $worker = makeWorkerWithProfile();

    $this->actingAs($worker)->post('/api/worker/profile', ['category_ids' => [99999]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['category_ids.0']);
});
