<?php

use App\Models\Barangay;
use App\Models\Booking;
use App\Models\JobPost;
use App\Models\RatingReview;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerPortfolioPhoto;
use App\Models\WorkerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeApprovedWorkerProfile(): WorkerProfile
{
    $barangay = Barangay::factory()->create();
    $user = User::factory()->create([
        'role' => 'worker',
        'email_verified_at' => now(),
        'barangay_id' => $barangay->id,
    ]);

    return WorkerProfile::factory()->create([
        'user_id' => $user->id,
        'verification_status' => 'approved',
        'bio' => 'Licensed electrician.',
    ]);
}

function makeAuthClient(): User
{
    $barangay = Barangay::factory()->create();

    return User::factory()->create([
        'role' => 'client',
        'email_verified_at' => now(),
        'barangay_id' => $barangay->id,
    ]);
}

it('returns 200 with correct shape for an approved worker', function () {
    $client = makeAuthClient();
    $profile = makeApprovedWorkerProfile();

    $response = $this->actingAs($client)->get("/api/workers/{$profile->id}");

    $response->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonStructure([
            'data' => [
                'worker_profile_id',
                'user_id',
                'name',
                'profile_photo_url',
                'bio',
                'barangay',
                'barangay_id',
                'distance_km',
                'distance_label',
                'categories',
                'portfolio_photos',
                'average_rating',
                'total_reviews',
                'completed_jobs',
                'trust_tier',
                'verification_status',
                'availability_status',
                'job_posts',
                'reviews',
            ],
        ]);

    expect($response->json('data.worker_profile_id'))->toBe($profile->id);
    expect($response->json('data.portfolio_photos'))->toBe([]);
});

it('includes saved portfolio photos in the public worker profile', function () {
    $client = makeAuthClient();
    $profile = makeApprovedWorkerProfile();
    $photo = WorkerPortfolioPhoto::create([
        'worker_profile_id' => $profile->id,
        'file_path' => 'worker-portfolios/1/work.jpg',
        'display_order' => 0,
    ]);

    $response = $this->actingAs($client)->get("/api/workers/{$profile->id}");

    $response->assertOk()
        ->assertJsonPath('data.portfolio_photos.0.id', $photo->id)
        ->assertJsonPath('data.portfolio_photos.0.url', asset('storage/'.$photo->file_path));
});

it('returns 404 for a non-existent worker_profile_id', function () {
    $client = makeAuthClient();

    $this->actingAs($client)->get('/api/workers/99999')
        ->assertStatus(404);
});

it('returns 200 when a client views an unverified worker (spec: verification is client-side concern)', function () {
    $client = makeAuthClient();
    $barangay = Barangay::factory()->create();
    $user = User::factory()->create(['role' => 'worker', 'email_verified_at' => now(), 'barangay_id' => $barangay->id]);
    $profile = WorkerProfile::factory()->create(['user_id' => $user->id, 'verification_status' => 'pending']);

    $this->actingAs($client)->get("/api/workers/{$profile->id}")
        ->assertStatus(200)
        ->assertJson(['success' => true]);
});

it('returns 404 when a client views a flagged or revoked worker', function () {
    $client = makeAuthClient();
    $barangay = Barangay::factory()->create();
    $user = User::factory()->create(['role' => 'worker', 'email_verified_at' => now(), 'barangay_id' => $barangay->id]);
    $profile = WorkerProfile::factory()->create(['user_id' => $user->id, 'verification_status' => 'approved', 'trust_tier' => 'flagged']);

    $this->actingAs($client)->get("/api/workers/{$profile->id}")
        ->assertStatus(404);
});

it('returns 200 when an admin views an unapproved worker', function () {
    $barangay = Barangay::factory()->create();
    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
        'barangay_id' => $barangay->id,
    ]);

    $workerBarangay = Barangay::factory()->create();
    $workerUser = User::factory()->create(['role' => 'worker', 'email_verified_at' => now(), 'barangay_id' => $workerBarangay->id]);
    $profile = WorkerProfile::factory()->create(['user_id' => $workerUser->id, 'verification_status' => 'pending']);

    $this->actingAs($admin)->get("/api/workers/{$profile->id}")
        ->assertStatus(200)
        ->assertJson(['success' => true]);
});

it('response includes reviews array with correct fields', function () {
    $client = makeAuthClient();
    $profile = makeApprovedWorkerProfile();

    $rater = User::factory()->create(['name' => 'Ana Cruz', 'email_verified_at' => now()]);
    $booking = Booking::factory()->create([
        'client_id' => $rater->id,
        'worker_id' => $profile->user_id,
        'status' => 'completed',
    ]);

    RatingReview::factory()->create([
        'booking_id' => $booking->id,
        'rated_by' => $rater->id,
        'rated_user' => $profile->user_id,
        'score' => 5,
        'comment' => 'Very professional!',
    ]);

    $response = $this->actingAs($client)->get("/api/workers/{$profile->id}");

    $response->assertStatus(200);
    $reviews = $response->json('data.reviews');
    expect($reviews)->toHaveCount(1);
    expect($reviews[0]['rated_by_name'])->toBe('Ana Cruz');
    expect($reviews[0]['score'])->toBe(5);
    expect($reviews[0]['comment'])->toBe('Very professional!');
    expect($reviews[0])->toHaveKey('created_at');
});

it('returns the actual completed bookings count even when the stored worker profile count is stale', function () {
    $client = makeAuthClient();
    $profile = makeApprovedWorkerProfile();
    $profile->update(['completed_jobs' => 0]);

    $category = ServiceCategory::factory()->create();
    for ($i = 0; $i < 4; $i++) {
        Booking::factory()->create([
            'client_id' => $client->id,
            'worker_id' => $profile->user_id,
            'service_category_id' => $category->id,
            'status' => 'completed',
        ]);
    }

    $response = $this->actingAs($client)->get("/api/workers/{$profile->id}");

    $response->assertStatus(200);
    expect($response->json('data.completed_jobs'))->toBe(4);
});

it('returns 401 for unauthenticated request', function () {
    $profile = makeApprovedWorkerProfile();

    $this->get("/api/workers/{$profile->id}")->assertStatus(401);
});

it('response includes job_posts with documented D3 shape', function () {
    $client = makeAuthClient();
    $profile = makeApprovedWorkerProfile();
    $category = ServiceCategory::factory()->create(['name' => 'Electrical Works']);

    $post = JobPost::factory()->create([
        'worker_profile_id' => $profile->id,
        'service_category_id' => $category->id,
        'title' => 'Expert Electrical Installation & Repair',
        'description' => 'Licensed electrical work with 5 years experience.',
        'rate_amount' => 300.00,
        'rate_type' => 'daily',
        'is_available' => true,
        'is_active' => true,
    ]);

    $response = $this->actingAs($client)->get("/api/workers/{$profile->id}");

    $response->assertStatus(200);
    $posts = $response->json('data.job_posts');
    expect($posts)->toHaveCount(1);
    expect($posts[0]['id'])->toBe($post->id);
    expect($posts[0]['category']['id'])->toBe($category->id);
    expect($posts[0]['category']['name'])->toBe('Electrical Works');
    expect($posts[0]['title'])->toBe('Expert Electrical Installation & Repair');
    expect($posts[0]['description'])->toBe('Licensed electrical work with 5 years experience.');
    expect((float) $posts[0]['rate_amount'])->toBe(300.00);
    expect($posts[0]['rate_type'])->toBe('daily');
    expect($posts[0]['rate_display'])->toBe('₱300.00/day');
    expect($posts[0]['is_available'])->toBe(true);
});
