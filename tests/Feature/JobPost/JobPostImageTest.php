<?php

use App\Models\Barangay;
use App\Models\JobPost;
use App\Models\JobPostImage;
use App\Models\ServiceCategory;
use App\Models\WorkerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeWorkerWithImagePost(array $postOverrides = []): array
{
    $worker  = makeApprovedBookingWorker();
    $profile = $worker->workerProfile;
    $cat     = ServiceCategory::factory()->create();

    $post = JobPost::factory()->create(array_merge([
        'worker_profile_id'   => $profile->id,
        'service_category_id' => $cat->id,
        'is_active'           => true,
        'is_available'        => true,
    ], $postOverrides));

    return [$worker, $profile, $post];
}

function fakeJpeg(string $name = 'photo.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($name, 800, 600);
}

// ══════════════════════════════════════════════════════════════════════════════
// UPLOAD
// ══════════════════════════════════════════════════════════════════════════════

it('worker can upload a single image to their post', function () {
    Storage::fake('public');
    [$worker, , $post] = makeWorkerWithImagePost();

    $this->actingAs($worker)
        ->postJson("/api/worker/posts/{$post->id}/images", [
            'images' => [fakeJpeg()],
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'images' => [['id', 'image_url', 'thumbnail_url', 'display_order']],
            ],
        ]);

    expect(JobPostImage::where('job_post_id', $post->id)->count())->toBe(1);
});

it('worker can upload multiple images to their post', function () {
    Storage::fake('public');
    [$worker, , $post] = makeWorkerWithImagePost();

    $this->actingAs($worker)
        ->postJson("/api/worker/posts/{$post->id}/images", [
            'images' => [fakeJpeg('a.jpg'), fakeJpeg('b.jpg'), fakeJpeg('c.jpg')],
        ])
        ->assertStatus(201);

    expect(JobPostImage::where('job_post_id', $post->id)->count())->toBe(3);
});

it('upload is rejected when total images would exceed 10', function () {
    Storage::fake('public');
    [$worker, , $post] = makeWorkerWithImagePost();

    // Seed 9 existing images
    JobPostImage::factory()->count(9)->create(['job_post_id' => $post->id]);

    // Uploading 2 more would push to 11
    $this->actingAs($worker)
        ->postJson("/api/worker/posts/{$post->id}/images", [
            'images' => [fakeJpeg('x.jpg'), fakeJpeg('y.jpg')],
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('upload is rejected for invalid file type', function () {
    Storage::fake('public');
    [$worker, , $post] = makeWorkerWithImagePost();

    $pdf = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

    $this->actingAs($worker)
        ->postJson("/api/worker/posts/{$post->id}/images", [
            'images' => [$pdf],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['images.0']);
});

it('upload is rejected when a file exceeds 10 MB', function () {
    Storage::fake('public');
    [$worker, , $post] = makeWorkerWithImagePost();

    $big = UploadedFile::fake()->create('big.jpg', 11_000, 'image/jpeg'); // 11 MB

    $this->actingAs($worker)
        ->postJson("/api/worker/posts/{$post->id}/images", [
            'images' => [$big],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['images.0']);
});

it('non-owner worker cannot upload images to another worker post', function () {
    Storage::fake('public');
    [, , $post]  = makeWorkerWithImagePost();
    $otherWorker = makeApprovedBookingWorker();

    $this->actingAs($otherWorker)
        ->postJson("/api/worker/posts/{$post->id}/images", [
            'images' => [fakeJpeg()],
        ])
        ->assertStatus(403);
});

// ══════════════════════════════════════════════════════════════════════════════
// DELETE
// ══════════════════════════════════════════════════════════════════════════════

it('worker can delete their own image and files are removed', function () {
    Storage::fake('public');
    [$worker, , $post] = makeWorkerWithImagePost();

    // Upload first so real paths are stored
    $this->actingAs($worker)
        ->postJson("/api/worker/posts/{$post->id}/images", ['images' => [fakeJpeg()]])
        ->assertStatus(201);

    $image = JobPostImage::where('job_post_id', $post->id)->first();

    Storage::disk('public')->assertExists($image->image_path);
    Storage::disk('public')->assertExists($image->thumbnail_path);

    $this->actingAs($worker)
        ->deleteJson("/api/worker/posts/{$post->id}/images/{$image->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    expect(JobPostImage::find($image->id))->toBeNull();
    Storage::disk('public')->assertMissing($image->image_path);
    Storage::disk('public')->assertMissing($image->thumbnail_path);
});

it('non-owner worker cannot delete another worker image', function () {
    Storage::fake('public');
    [$worker, , $post] = makeWorkerWithImagePost();
    $otherWorker       = makeApprovedBookingWorker();

    $image = JobPostImage::factory()->create(['job_post_id' => $post->id]);

    $this->actingAs($otherWorker)
        ->deleteJson("/api/worker/posts/{$post->id}/images/{$image->id}")
        ->assertStatus(403);
});

it('image deletion re-numbers remaining images from zero', function () {
    Storage::fake('public');
    [$worker, , $post] = makeWorkerWithImagePost();

    $this->actingAs($worker)
        ->postJson("/api/worker/posts/{$post->id}/images", [
            'images' => [fakeJpeg('a.jpg'), fakeJpeg('b.jpg'), fakeJpeg('c.jpg')],
        ])
        ->assertStatus(201);

    $images = JobPostImage::where('job_post_id', $post->id)->orderBy('display_order')->get();

    // Delete the middle image
    $this->actingAs($worker)
        ->deleteJson("/api/worker/posts/{$post->id}/images/{$images[1]->id}")
        ->assertStatus(200);

    $remaining = JobPostImage::where('job_post_id', $post->id)->orderBy('display_order')->pluck('display_order')->all();
    expect($remaining)->toBe([0, 1]);
});

// ══════════════════════════════════════════════════════════════════════════════
// REORDER
// ══════════════════════════════════════════════════════════════════════════════

it('worker can reorder images and order is persisted', function () {
    Storage::fake('public');
    [$worker, , $post] = makeWorkerWithImagePost();

    $this->actingAs($worker)
        ->postJson("/api/worker/posts/{$post->id}/images", [
            'images' => [fakeJpeg('a.jpg'), fakeJpeg('b.jpg'), fakeJpeg('c.jpg')],
        ])
        ->assertStatus(201);

    $ids = JobPostImage::where('job_post_id', $post->id)->orderBy('display_order')->pluck('id')->all();
    $reversed = array_reverse($ids);

    $this->actingAs($worker)
        ->putJson("/api/worker/posts/{$post->id}/images/order", ['image_ids' => $reversed])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $newOrder = JobPostImage::where('job_post_id', $post->id)->orderBy('display_order')->pluck('id')->all();
    expect($newOrder)->toBe($reversed);
});

it('reorder is rejected when image_ids does not match post images exactly', function () {
    Storage::fake('public');
    [$worker, , $post] = makeWorkerWithImagePost();

    $image = JobPostImage::factory()->create(['job_post_id' => $post->id]);

    $this->actingAs($worker)
        ->putJson("/api/worker/posts/{$post->id}/images/order", [
            'image_ids' => [$image->id, 9999], // 9999 doesn't belong
        ])
        ->assertStatus(422);
});

it('non-owner worker cannot reorder images', function () {
    Storage::fake('public');
    [, , $post]  = makeWorkerWithImagePost();
    $otherWorker = makeApprovedBookingWorker();

    $image = JobPostImage::factory()->create(['job_post_id' => $post->id]);

    $this->actingAs($otherWorker)
        ->putJson("/api/worker/posts/{$post->id}/images/order", ['image_ids' => [$image->id]])
        ->assertStatus(403);
});

// ══════════════════════════════════════════════════════════════════════════════
// FEED — images included
// ══════════════════════════════════════════════════════════════════════════════

it('feed includes ordered image metadata for each post', function () {
    Storage::fake('public');
    $barangay = Barangay::factory()->create(['latitude' => 9.9545, 'longitude' => 124.3656]);
    $client   = User::factory()->create(['role' => 'client', 'barangay_id' => $barangay->id]);

    $worker  = User::factory()->create(['role' => 'worker', 'barangay_id' => $barangay->id]);
    $profile = WorkerProfile::factory()->create([
        'user_id'             => $worker->id,
        'verification_status' => 'approved',
    ]);
    $cat  = ServiceCategory::factory()->create(['is_active' => true]);
    $post = JobPost::factory()->create([
        'worker_profile_id'   => $profile->id,
        'service_category_id' => $cat->id,
        'is_active'           => true,
        'is_available'        => true,
    ]);

    JobPostImage::factory()->create(['job_post_id' => $post->id, 'display_order' => 0]);
    JobPostImage::factory()->create(['job_post_id' => $post->id, 'display_order' => 1]);

    $response = $this->actingAs($client)->getJson('/api/feed')->assertStatus(200);

    $images = $response->json('data.posts.0.images');
    expect($images)->toHaveCount(2);
    expect($images[0]['display_order'])->toBe(0);
    expect($images[1]['display_order'])->toBe(1);
    expect($images[0])->toHaveKeys(['id', 'thumbnail_url', 'image_url', 'display_order']);
});

// ══════════════════════════════════════════════════════════════════════════════
// POST DETAIL
// ══════════════════════════════════════════════════════════════════════════════

it('client can retrieve an active post detail', function () {
    Storage::fake('public');
    $barangay = Barangay::factory()->create();
    $client   = User::factory()->create(['role' => 'client', 'barangay_id' => $barangay->id]);

    $worker  = User::factory()->create(['role' => 'worker', 'barangay_id' => $barangay->id]);
    $profile = WorkerProfile::factory()->create([
        'user_id'             => $worker->id,
        'verification_status' => 'approved',
    ]);
    $cat  = ServiceCategory::factory()->create();
    $post = JobPost::factory()->create([
        'worker_profile_id'   => $profile->id,
        'service_category_id' => $cat->id,
        'is_active'           => true,
        'is_available'        => true,
    ]);
    JobPostImage::factory()->create(['job_post_id' => $post->id, 'display_order' => 0]);

    $this->actingAs($client)
        ->getJson("/api/posts/{$post->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'job_post' => [
                    'id', 'worker_profile_id', 'service_category_id',
                    'worker', 'category',
                    'title', 'description', 'rate_amount', 'rate_type', 'rate_display',
                    'is_available', 'is_active',
                    'images' => [['id', 'image_url', 'thumbnail_url', 'display_order']],
                    'created_at',
                ],
            ],
        ]);
});

it('inactive post detail returns 404', function () {
    $client  = makeBookingClient();
    $barangay = Barangay::factory()->create();
    $worker  = User::factory()->create(['role' => 'worker', 'barangay_id' => $barangay->id]);
    $profile = WorkerProfile::factory()->create(['user_id' => $worker->id, 'verification_status' => 'approved']);
    $cat     = ServiceCategory::factory()->create();

    $post = JobPost::factory()->create([
        'worker_profile_id'   => $profile->id,
        'service_category_id' => $cat->id,
        'is_active'           => false,
        'is_available'        => true,
    ]);

    $this->actingAs($client)
        ->getJson("/api/posts/{$post->id}")
        ->assertStatus(404);
});

it('soft-deleted post detail returns 404', function () {
    $client  = makeBookingClient();
    $barangay = Barangay::factory()->create();
    $worker  = User::factory()->create(['role' => 'worker', 'barangay_id' => $barangay->id]);
    $profile = WorkerProfile::factory()->create(['user_id' => $worker->id, 'verification_status' => 'approved']);
    $cat     = ServiceCategory::factory()->create();

    $post = JobPost::factory()->create([
        'worker_profile_id'   => $profile->id,
        'service_category_id' => $cat->id,
        'is_active'           => true,
        'is_available'        => true,
    ]);
    $post->delete();

    $this->actingAs($client)
        ->getJson("/api/posts/{$post->id}")
        ->assertStatus(404);
});

// ══════════════════════════════════════════════════════════════════════════════
// BOOKING COMPATIBILITY
// ══════════════════════════════════════════════════════════════════════════════

it('booking still accepts job_post_id', function () {
    $client   = makeBookingClient();
    $worker   = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    $post = JobPost::factory()->create([
        'worker_profile_id'   => $worker->workerProfile->id,
        'service_category_id' => $category->id,
        'is_active'           => true,
    ]);

    $this->actingAs($client)
        ->postJson('/api/bookings', [
            'worker_id'           => $worker->id,
            'service_category_id' => $category->id,
            'job_post_id'         => $post->id,
            'scheduled_at'        => now()->addDays(3)->toDateTimeString(),
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    expect(\App\Models\Booking::latest()->first()->job_post_id)->toBe($post->id);
});
