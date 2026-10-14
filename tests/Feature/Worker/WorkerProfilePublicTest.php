<?php

use App\Models\Barangay;
use App\Models\Booking;
use App\Models\JobPost;
use App\Models\JobPostImage;
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
                'is_online',
                'job_posts',
                'reviews',
            ],
        ]);

    expect($response->json('data.worker_profile_id'))->toBe($profile->id);
    expect($response->json('data.portfolio_photos'))->toBe([]);
});

it('returns the authenticated worker profile even when the user id matches another profile id', function () {
    makeAuthClient();
    makeAuthClient();
    $worker = makeApprovedWorkerProfile();
    $worker->update([
        'average_rating' => 4.75,
        'total_reviews' => 9,
        'completed_jobs' => 8,
        'trust_tier' => 'verified',
    ]);

    while (! WorkerProfile::whereKey($worker->user_id)->exists()) {
        makeApprovedWorkerProfile();
    }
    $otherProfile = WorkerProfile::findOrFail($worker->user_id);
    $otherProfile->update([
        'average_rating' => 1.25,
        'total_reviews' => 2,
        'completed_jobs' => 1,
        'trust_tier' => null,
    ]);

    $reviewer = User::factory()->create(['name' => 'Own reviewer', 'email_verified_at' => now()]);
    $otherReviewer = User::factory()->create(['name' => 'Other reviewer', 'email_verified_at' => now()]);
    $ownBooking = Booking::factory()->create([
        'client_id' => $reviewer->id,
        'worker_id' => $worker->user_id,
        'status' => 'completed',
    ]);
    $otherBooking = Booking::factory()->create([
        'client_id' => $otherReviewer->id,
        'worker_id' => $otherProfile->user_id,
        'status' => 'completed',
    ]);
    Booking::factory()->count(2)->create([
        'client_id' => $reviewer->id,
        'worker_id' => $worker->user_id,
        'status' => 'completed',
    ]);
    RatingReview::factory()->create([
        'booking_id' => $ownBooking->id,
        'rated_by' => $reviewer->id,
        'rated_user' => $worker->user_id,
        'score' => 5,
        'comment' => 'This belongs to the signed-in worker.',
    ]);
    RatingReview::factory()->create([
        'booking_id' => $otherBooking->id,
        'rated_by' => $otherReviewer->id,
        'rated_user' => $otherProfile->user_id,
        'score' => 1,
        'comment' => 'This belongs to a different worker.',
    ]);

    expect($worker->user_id)->toBe($otherProfile->id)
        ->and($worker->id)->not->toBe($otherProfile->id);

    $response = $this->actingAs($worker->user)->get('/api/worker/profile')
        ->assertOk()
        ->assertJsonPath('data.worker.worker_profile_id', $worker->id)
        ->assertJsonPath('data.worker.user_id', $worker->user_id)
        ->assertJsonPath('data.worker.average_rating', 4.75)
        ->assertJsonPath('data.worker.completed_jobs', 3)
        ->assertJsonPath('data.worker.trust_tier', 'verified');

    expect($response->json('data.worker.reviews'))->toHaveCount(1)
        ->and($response->json('data.worker.reviews.0.comment'))
        ->toBe('This belongs to the signed-in worker.');
});

it('reports worker online presence from a recent heartbeat', function () {
    $client = makeAuthClient();
    $profile = makeApprovedWorkerProfile();
    $profile->user->forceFill(['last_seen_at' => now()->subMinutes(4)])->save();

    $this->actingAs($client)->get("/api/workers/{$profile->id}")
        ->assertOk()
        ->assertJsonPath('data.is_online', true);
});

it('reports workers offline when their heartbeat is absent or stale', function () {
    $client = makeAuthClient();
    $profile = makeApprovedWorkerProfile();

    $this->actingAs($client)->get("/api/workers/{$profile->id}")
        ->assertOk()
        ->assertJsonPath('data.is_online', false);

    $profile->user->forceFill(['last_seen_at' => now()->subMinutes(6)])->save();
    $this->actingAs($client)->get("/api/workers/{$profile->id}")
        ->assertOk()
        ->assertJsonPath('data.is_online', false);
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

it('prefers a worker profile ID over another worker user ID when they collide', function () {
    $client = makeAuthClient();
    $workers = collect(range(1, 20))->map(function () {
        $user = User::factory()->create([
            'role' => 'worker',
            'email_verified_at' => now(),
        ]);
        $profile = WorkerProfile::factory()->create([
            'user_id' => $user->id,
            'verification_status' => 'approved',
        ]);

        return compact('user', 'profile');
    });

    $collision = $workers->first(function ($target) use ($workers) {
        return $workers->contains(
            fn ($owner) => (int) $owner['user']->id === (int) $target['profile']->id
                && (int) $owner['profile']->id !== (int) $target['profile']->id,
        );
    });
    expect($collision)->not->toBeNull();

    $targetProfile = $collision['profile'];
    $this->actingAs($client)->get("/api/workers/{$targetProfile->id}")
        ->assertOk()
        ->assertJsonPath('data.worker_profile_id', $targetProfile->id);
});

it('allows a client to load a flagged or revoked worker profile to continue from a feed post', function () {
    $client = makeAuthClient();
    foreach (['flagged', 'revoked'] as $trustTier) {
        $barangay = Barangay::factory()->create();
        $user = User::factory()->create([
            'role' => 'worker',
            'email_verified_at' => now(),
            'barangay_id' => $barangay->id,
        ]);
        $profile = WorkerProfile::factory()->create([
            'user_id' => $user->id,
            'verification_status' => 'approved',
            'trust_tier' => $trustTier,
        ]);

        $this->actingAs($client)->get("/api/workers/{$profile->id}")
            ->assertOk()
            ->assertJsonPath('data.worker_profile_id', $profile->id)
            ->assertJsonPath('data.trust_tier', $trustTier);
    }
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

    $rater = User::factory()->create([
        'name' => 'Ana Cruz',
        'email_verified_at' => now(),
        'profile_photo_path' => 'https://lh3.googleusercontent.com/ana-photo',
    ]);
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
    expect($reviews[0]['booking_id'])->toBe($booking->id);
    expect($reviews[0]['rated_by_user_id'])->toBe($rater->id);
    expect($reviews[0]['rated_by_name'])->toBe('Ana Cruz');
    expect($reviews[0]['reviewer_avatar_url'])->toBe('https://lh3.googleusercontent.com/ana-photo');
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
    $image = JobPostImage::factory()->create([
        'job_post_id' => $post->id,
        'image_path' => "job_posts/{$post->id}/work.jpg",
        'thumbnail_path' => "job_posts/{$post->id}/work_thumb.jpg",
        'display_order' => 0,
    ]);
    JobPost::factory()->create([
        'worker_profile_id' => $profile->id,
        'service_category_id' => $category->id,
        'is_available' => false,
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
    expect($posts[0]['is_active'])->toBeTrue();
    expect($posts[0]['images'][0]['id'])->toBe($image->id);
    expect($posts[0]['images'][0]['image_url'])->toBe(asset('storage/'.$image->image_path));
    expect($posts[0]['is_available'])->toBe(true);
});
