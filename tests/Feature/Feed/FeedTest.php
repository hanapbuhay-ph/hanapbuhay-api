<?php

use App\Models\Barangay;
use App\Models\JobPost;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Create a client with a known barangay.
 */
function makeFeedClient(?int $barangayId = null): User
{
    $barangay = $barangayId
        ? Barangay::find($barangayId)
        : Barangay::factory()->create(['latitude' => 9.9545, 'longitude' => 124.3656]);

    return User::factory()->create(['role' => 'client', 'barangay_id' => $barangay->id]);
}

/**
 * Create a worker with a job post and return [$worker, $profile, $post, $category].
 * Workers default to unverified — matching real registration behaviour.
 * Pass verification_status in $profileOverrides to override.
 */
function makeWorkerWithPost(array $postOverrides = [], ?int $barangayId = null): array
{
    $barangay = $barangayId
        ? Barangay::find($barangayId)
        : Barangay::factory()->create(['latitude' => 9.9545, 'longitude' => 124.3656]);

    $worker = User::factory()->create(['role' => 'worker', 'barangay_id' => $barangay->id]);
    $profile = WorkerProfile::factory()->create([
        'user_id' => $worker->id,
        'verification_status' => 'unverified',
        'trust_tier' => null,
        'availability_status' => 'available',
    ]);

    $category = ServiceCategory::factory()->create(['is_active' => true]);

    $post = JobPost::factory()->create(array_merge([
        'worker_profile_id' => $profile->id,
        'service_category_id' => $category->id,
        'is_active' => true,
        'is_available' => true,
    ], $postOverrides));

    return [$worker, $profile, $post, $category];
}

// ══════════════════════════════════════════════════════════════════════════════
// BASIC ACCESS
// ══════════════════════════════════════════════════════════════════════════════

it('returns 401 when unauthenticated', function () {
    $this->getJson('/api/feed')->assertStatus(401);
});

it('returns 200 with expected structure for authenticated client', function () {
    $client = makeFeedClient();
    [, , $post] = makeWorkerWithPost();

    $this->actingAs($client)
        ->getJson('/api/feed')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'posts' => [[
                    'job_post_id', 'worker_profile_id',
                    'worker' => ['user_id', 'name', 'distance_km', 'distance_label', 'trust_tier', 'average_rating', 'availability_status'],
                    'category' => ['id', 'name', 'icon'],
                    'title', 'rate_amount', 'rate_type', 'rate_display', 'is_available', 'posted_at',
                ]],
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ]);
});

it('returns empty posts array when no active job posts exist', function () {
    $client = makeFeedClient();

    $response = $this->actingAs($client)->getJson('/api/feed')->assertStatus(200);

    expect($response->json('data.posts'))->toBeEmpty();
    expect($response->json('data.pagination.total'))->toBe(0);
});

// ══════════════════════════════════════════════════════════════════════════════
// VISIBILITY RULES
// ══════════════════════════════════════════════════════════════════════════════

it('does not show posts from inactive workers (is_active = false)', function () {
    $client = makeFeedClient();
    [, $profile, $post] = makeWorkerWithPost();

    // Deactivate the worker account
    $profile->user->update(['is_active' => false]);

    $response = $this->actingAs($client)->getJson('/api/feed')->assertStatus(200);

    expect($response->json('data.posts'))->toBeEmpty();
});

it('shows posts from flagged workers in the client feed', function () {
    $client = makeFeedClient();
    [, $profile] = makeWorkerWithPost();

    $profile->update(['trust_tier' => 'flagged']);

    $response = $this->actingAs($client)->getJson('/api/feed')->assertStatus(200);

    expect(collect($response->json('data.posts'))->pluck('job_post_id'))
        ->toContain($profile->jobPosts()->first()->id);
});

it('shows posts from revoked workers in the client feed', function () {
    $client = makeFeedClient();
    [, $profile] = makeWorkerWithPost();

    $profile->update(['trust_tier' => 'revoked']);

    $response = $this->actingAs($client)->getJson('/api/feed')->assertStatus(200);

    expect(collect($response->json('data.posts'))->pluck('job_post_id'))
        ->toContain($profile->jobPosts()->first()->id);
});

it('does not show inactive (soft-deleted) job posts', function () {
    $client = makeFeedClient();
    [, , $post] = makeWorkerWithPost();

    $post->delete(); // soft delete

    $response = $this->actingAs($client)->getJson('/api/feed')->assertStatus(200);

    expect($response->json('data.posts'))->toBeEmpty();
});

it('does not show unavailable job posts by default', function () {
    $client = makeFeedClient();
    makeWorkerWithPost(['is_available' => false]);

    $response = $this->actingAs($client)->getJson('/api/feed')->assertStatus(200);

    expect($response->json('data.posts'))->toBeEmpty();
});

it('shows unverified workers in the default feed (spec: verification is client-side concern)', function () {
    $client = makeFeedClient();
    [, $profile, $post] = makeWorkerWithPost();

    // Worker is unverified — should still appear in the feed
    expect($profile->verification_status)->toBe('unverified');

    $response = $this->actingAs($client)->getJson('/api/feed')->assertStatus(200);

    $postIds = collect($response->json('data.posts'))->pluck('job_post_id');
    expect($postIds)->toContain($post->id);
});

// ══════════════════════════════════════════════════════════════════════════════
// FILTERS
// ══════════════════════════════════════════════════════════════════════════════

it('filters by category_id', function () {
    $client = makeFeedClient();
    $category1 = ServiceCategory::factory()->create(['is_active' => true]);
    $category2 = ServiceCategory::factory()->create(['is_active' => true]);

    $barangay = Barangay::factory()->create();
    $worker = User::factory()->create(['role' => 'worker', 'barangay_id' => $barangay->id]);
    $profile = WorkerProfile::factory()->create(['user_id' => $worker->id, 'verification_status' => 'approved']);

    $post1 = JobPost::factory()->create(['worker_profile_id' => $profile->id, 'service_category_id' => $category1->id, 'is_active' => true, 'is_available' => true]);
    $post2 = JobPost::factory()->create(['worker_profile_id' => $profile->id, 'service_category_id' => $category2->id, 'is_active' => true, 'is_available' => true]);

    $response = $this->actingAs($client)
        ->getJson("/api/feed?category_id={$category1->id}")
        ->assertStatus(200);

    $postIds = collect($response->json('data.posts'))->pluck('job_post_id');
    expect($postIds)->toContain($post1->id);
    expect($postIds)->not->toContain($post2->id);
});

it('filters by rate_type', function () {
    $client = makeFeedClient();
    [, , $hourlyPost] = makeWorkerWithPost(['rate_type' => 'hourly']);
    [, , $dailyPost] = makeWorkerWithPost(['rate_type' => 'daily']);

    $response = $this->actingAs($client)
        ->getJson('/api/feed?rate_type=hourly')
        ->assertStatus(200);

    $postIds = collect($response->json('data.posts'))->pluck('job_post_id');
    expect($postIds)->toContain($hourlyPost->id);
    expect($postIds)->not->toContain($dailyPost->id);
});

it('filters verification=verified returns only workers with trust_tier set', function () {
    $client = makeFeedClient();

    // verified worker
    [, $verifiedProfile, $verifiedPost] = makeWorkerWithPost();
    $verifiedProfile->update(['trust_tier' => 'verified']);

    // unverified worker (trust_tier = null)
    [, , $unverifiedPost] = makeWorkerWithPost();

    $response = $this->actingAs($client)
        ->getJson('/api/feed?verification=verified')
        ->assertStatus(200);

    $postIds = collect($response->json('data.posts'))->pluck('job_post_id');
    expect($postIds)->toContain($verifiedPost->id);
    expect($postIds)->not->toContain($unverifiedPost->id);
});

it('filters verification=unverified returns only workers without trust_tier', function () {
    $client = makeFeedClient();

    [, $verifiedProfile, $verifiedPost] = makeWorkerWithPost();
    $verifiedProfile->update(['trust_tier' => 'verified']);

    [, , $unverifiedPost] = makeWorkerWithPost();

    $response = $this->actingAs($client)
        ->getJson('/api/feed?verification=unverified')
        ->assertStatus(200);

    $postIds = collect($response->json('data.posts'))->pluck('job_post_id');
    expect($postIds)->toContain($unverifiedPost->id);
    expect($postIds)->not->toContain($verifiedPost->id);
});

it('filters availability=available returns only workers with availability_status=available', function () {
    $client = makeFeedClient();

    [, $availableProfile, $availablePost] = makeWorkerWithPost();
    $availableProfile->update(['availability_status' => 'available']);

    [, $busyProfile, $busyPost] = makeWorkerWithPost();
    $busyProfile->update(['availability_status' => 'busy']);

    $response = $this->actingAs($client)
        ->getJson('/api/feed?availability=available')
        ->assertStatus(200);

    $postIds = collect($response->json('data.posts'))->pluck('job_post_id');
    expect($postIds)->toContain($availablePost->id);
    expect($postIds)->not->toContain($busyPost->id);
});

it('returns each worker availability status for the client feed card', function () {
    $client = makeFeedClient();
    [, $availableProfile, $availablePost] = makeWorkerWithPost();
    $availableProfile->update(['availability_status' => 'available']);

    [, $busyProfile, $busyPost] = makeWorkerWithPost();
    $busyProfile->update(['availability_status' => 'busy']);

    [, $offlineProfile, $offlinePost] = makeWorkerWithPost();
    $offlineProfile->update(['availability_status' => 'offline']);

    $posts = collect(
        $this->actingAs($client)->getJson('/api/feed')->assertOk()->json('data.posts'),
    )->keyBy('job_post_id');

    expect($posts[$availablePost->id]['worker']['availability_status'])->toBe('available');
    expect($posts[$busyPost->id]['worker']['availability_status'])->toBe('busy');
    expect($posts[$offlinePost->id]['worker']['availability_status'])->toBe('offline');
});

it('returns an external worker profile photo URL without prepending storage', function () {
    $client = makeFeedClient();
    [$worker] = makeWorkerWithPost();
    $photoUrl = 'https://profiles.example.test/worker-photo.jpg';
    $worker->update(['profile_photo_path' => $photoUrl]);

    $response = $this->actingAs($client)->getJson('/api/feed')->assertOk();

    expect($response->json('data.posts.0.worker.profile_photo_url'))->toBe($photoUrl);
});

// ══════════════════════════════════════════════════════════════════════════════
// SORT ORDER
// ══════════════════════════════════════════════════════════════════════════════

it('trusted workers appear before verified workers in the feed', function () {
    $client = makeFeedClient();

    [, $verifiedProfile, $verifiedPost] = makeWorkerWithPost();
    $verifiedProfile->update(['trust_tier' => 'verified', 'average_rating' => 3.0]);

    [, $trustedProfile, $trustedPost] = makeWorkerWithPost();
    $trustedProfile->update(['trust_tier' => 'trusted', 'average_rating' => 3.0]);

    $response = $this->actingAs($client)->getJson('/api/feed')->assertStatus(200);

    $postIds = collect($response->json('data.posts'))->pluck('job_post_id')->values()->all();
    $trustedIdx = array_search($trustedPost->id, $postIds);
    $verifiedIdx = array_search($verifiedPost->id, $postIds);

    expect($trustedIdx)->toBeLessThan($verifiedIdx);
});

it('within the same tier, higher-rated workers appear first', function () {
    $client = makeFeedClient();

    [, $lowProfile, $lowPost] = makeWorkerWithPost();
    $lowProfile->update(['trust_tier' => 'verified', 'average_rating' => 2.0]);

    [, $highProfile, $highPost] = makeWorkerWithPost();
    $highProfile->update(['trust_tier' => 'verified', 'average_rating' => 5.0]);

    $response = $this->actingAs($client)->getJson('/api/feed')->assertStatus(200);

    $postIds = collect($response->json('data.posts'))->pluck('job_post_id')->values()->all();
    $highIdx = array_search($highPost->id, $postIds);
    $lowIdx = array_search($lowPost->id, $postIds);

    expect($highIdx)->toBeLessThan($lowIdx);
});

// ══════════════════════════════════════════════════════════════════════════════
// PAGINATION
// ══════════════════════════════════════════════════════════════════════════════

it('paginates results with 15 per page', function () {
    $client = makeFeedClient();

    // Create 20 job posts
    for ($i = 0; $i < 20; $i++) {
        makeWorkerWithPost();
    }

    $response = $this->actingAs($client)
        ->getJson('/api/feed?page=1')
        ->assertStatus(200);

    expect(count($response->json('data.posts')))->toBe(15);
    expect($response->json('data.pagination.total'))->toBe(20);
    expect($response->json('data.pagination.last_page'))->toBe(2);
});

it('page 2 returns the remaining posts', function () {
    $client = makeFeedClient();

    for ($i = 0; $i < 20; $i++) {
        makeWorkerWithPost();
    }

    $response = $this->actingAs($client)
        ->getJson('/api/feed?page=2')
        ->assertStatus(200);

    expect(count($response->json('data.posts')))->toBe(5);
    expect($response->json('data.pagination.current_page'))->toBe(2);
});
