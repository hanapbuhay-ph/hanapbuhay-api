<?php

use App\Models\JobPost;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Valid payload for creating a job post. */
function validPostPayload(int $categoryId): array
{
    return [
        'service_category_id' => $categoryId,
        'title'               => 'Expert Aircon Cleaning & Repair',
        'description'         => 'Professional aircon cleaning with over 3 years of experience.',
        'rate_amount'         => 300.00,
        'rate_type'           => 'per_session',
        'is_available'        => true,
    ];
}

// ══════════════════════════════════════════════════════════════════════════════
// CREATE
// ══════════════════════════════════════════════════════════════════════════════

it('worker can create a job post (happy path)', function () {
    $worker   = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    $this->actingAs($worker)
        ->postJson('/api/worker/posts', validPostPayload($category->id))
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Job post created.')
        ->assertJsonStructure([
            'data' => [
                'job_post' => [
                    'id', 'category', 'title', 'description',
                    'rate_amount', 'rate_type', 'rate_display',
                    'is_available', 'is_active', 'created_at',
                ],
            ],
        ]);
});

it('rate_display is formatted correctly for per_session type', function () {
    $worker   = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    $response = $this->actingAs($worker)
        ->postJson('/api/worker/posts', validPostPayload($category->id))
        ->assertStatus(201);

    expect($response->json('data.job_post.rate_display'))->toBe('From ₱300.00/session');
});

it('creating a second post in the same category replaces the old one (one-per-category rule)', function () {
    $worker   = makeApprovedBookingWorker();
    $profile  = $worker->workerProfile;
    $category = ServiceCategory::factory()->create();

    // Create first post
    $first = JobPost::create([
        'worker_profile_id'   => $profile->id,
        'service_category_id' => $category->id,
        'title'               => 'Old Post',
        'description'         => 'Old description.',
        'rate_amount'         => 200.00,
        'rate_type'           => 'hourly',
        'is_available'        => true,
        'is_active'           => true,
    ]);

    // Create second post in same category via API
    $this->actingAs($worker)
        ->postJson('/api/worker/posts', validPostPayload($category->id))
        ->assertStatus(201)
        ->assertJsonPath('data.job_post.title', 'Expert Aircon Cleaning & Repair');

    // Old post should be soft-deleted
    expect(JobPost::find($first->id))->toBeNull();
    expect(JobPost::withTrashed()->find($first->id))->not->toBeNull();
});

it('returns 403 when a client tries to create a job post', function () {
    $client   = makeBookingClient();
    $category = ServiceCategory::factory()->create();

    $this->actingAs($client)
        ->postJson('/api/worker/posts', validPostPayload($category->id))
        ->assertStatus(403);
});

it('returns 401 when unauthenticated', function () {
    $this->postJson('/api/worker/posts', [])->assertStatus(401);
});

it('returns 422 when required fields are missing', function () {
    $worker = makeApprovedBookingWorker();

    $this->actingAs($worker)
        ->postJson('/api/worker/posts', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'service_category_id', 'title', 'description', 'rate_amount', 'rate_type',
        ]);
});

it('returns 422 when rate_type is invalid', function () {
    $worker   = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    $payload              = validPostPayload($category->id);
    $payload['rate_type'] = 'per_banana';

    $this->actingAs($worker)
        ->postJson('/api/worker/posts', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['rate_type']);
});

it('returns 422 when service_category_id does not exist', function () {
    $worker = makeApprovedBookingWorker();

    $payload                        = validPostPayload(9999);
    $payload['service_category_id'] = 9999;

    $this->actingAs($worker)
        ->postJson('/api/worker/posts', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['service_category_id']);
});

// ══════════════════════════════════════════════════════════════════════════════
// LIST
// ══════════════════════════════════════════════════════════════════════════════

it('worker can list their own job posts', function () {
    $worker   = makeApprovedBookingWorker();
    $profile  = $worker->workerProfile;
    $category = ServiceCategory::factory()->create();

    JobPost::factory()->create([
        'worker_profile_id'   => $profile->id,
        'service_category_id' => $category->id,
    ]);

    $this->actingAs($worker)
        ->getJson('/api/worker/posts')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'posts' => [['id', 'category', 'title', 'rate_display', 'is_available', 'is_active']],
            ],
        ]);
});

it('list does not return inactive (soft-deleted) posts by default', function () {
    $worker   = makeApprovedBookingWorker();
    $profile  = $worker->workerProfile;
    $category = ServiceCategory::factory()->create();

    $post = JobPost::factory()->create([
        'worker_profile_id'   => $profile->id,
        'service_category_id' => $category->id,
        'is_active'           => true,
    ]);
    $post->delete(); // soft delete

    $response = $this->actingAs($worker)
        ->getJson('/api/worker/posts')
        ->assertStatus(200);

    expect($response->json('data.posts'))->toBeEmpty();
});

it('list includes inactive posts when include_inactive=true', function () {
    $worker   = makeApprovedBookingWorker();
    $profile  = $worker->workerProfile;
    $category = ServiceCategory::factory()->create();

    JobPost::factory()->create([
        'worker_profile_id'   => $profile->id,
        'service_category_id' => $category->id,
        'is_active'           => false,
    ]);

    $response = $this->actingAs($worker)
        ->getJson('/api/worker/posts?include_inactive=true')
        ->assertStatus(200);

    expect($response->json('data.posts'))->not->toBeEmpty();
});

// ══════════════════════════════════════════════════════════════════════════════
// UPDATE
// ══════════════════════════════════════════════════════════════════════════════

it('worker can update their own job post', function () {
    $worker   = makeApprovedBookingWorker();
    $profile  = $worker->workerProfile;
    $category = ServiceCategory::factory()->create();

    $post = JobPost::factory()->create([
        'worker_profile_id'   => $profile->id,
        'service_category_id' => $category->id,
    ]);

    $this->actingAs($worker)
        ->putJson("/api/worker/posts/{$post->id}", [
            'title'       => 'Updated Title',
            'rate_amount' => 450.00,
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Job post updated.')
        ->assertJsonPath('data.job_post.title', 'Updated Title');

    expect($post->fresh()->title)->toBe('Updated Title');
    expect((float) $post->fresh()->rate_amount)->toBe(450.00);
});

it('returns 403 when worker tries to update another worker\'s post', function () {
    $worker1 = makeApprovedBookingWorker();
    $worker2 = makeApprovedBookingWorker();
    $profile2 = $worker2->workerProfile;
    $category = ServiceCategory::factory()->create();

    $post = JobPost::factory()->create([
        'worker_profile_id'   => $profile2->id,
        'service_category_id' => $category->id,
    ]);

    $this->actingAs($worker1)
        ->putJson("/api/worker/posts/{$post->id}", ['title' => 'Stolen'])
        ->assertStatus(403);
});

it('returns 404 when updating a non-existent post', function () {
    $worker = makeApprovedBookingWorker();

    $this->actingAs($worker)
        ->putJson('/api/worker/posts/99999', ['title' => 'Ghost'])
        ->assertStatus(404);
});

// ══════════════════════════════════════════════════════════════════════════════
// DEACTIVATE (DELETE)
// ══════════════════════════════════════════════════════════════════════════════

it('worker can deactivate their own job post', function () {
    $worker   = makeApprovedBookingWorker();
    $profile  = $worker->workerProfile;
    $category = ServiceCategory::factory()->create();

    $post = JobPost::factory()->create([
        'worker_profile_id'   => $profile->id,
        'service_category_id' => $category->id,
    ]);

    $this->actingAs($worker)
        ->deleteJson("/api/worker/posts/{$post->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Job post deactivated. It is no longer visible to clients.');

    // Soft-deleted — should not appear in normal queries
    expect(JobPost::find($post->id))->toBeNull();
    // But should exist with trashed
    expect(JobPost::withTrashed()->find($post->id))->not->toBeNull();
});

it('returns 403 when worker tries to deactivate another worker\'s post', function () {
    $worker1 = makeApprovedBookingWorker();
    $worker2 = makeApprovedBookingWorker();
    $profile2 = $worker2->workerProfile;
    $category = ServiceCategory::factory()->create();

    $post = JobPost::factory()->create([
        'worker_profile_id'   => $profile2->id,
        'service_category_id' => $category->id,
    ]);

    $this->actingAs($worker1)
        ->deleteJson("/api/worker/posts/{$post->id}")
        ->assertStatus(403);
});

it('returns 404 when deactivating a non-existent post', function () {
    $worker = makeApprovedBookingWorker();

    $this->actingAs($worker)
        ->deleteJson('/api/worker/posts/99999')
        ->assertStatus(404);
});
