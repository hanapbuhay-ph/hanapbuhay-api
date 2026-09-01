<?php

use App\Models\Barangay;
use App\Models\Booking;
use App\Models\RatingReview;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerProfile;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeApprovedWorkerProfile(): WorkerProfile
{
    $barangay = Barangay::factory()->create();
    $user     = User::factory()->create([
        'role'              => 'worker',
        'email_verified_at' => now(),
        'barangay_id'       => $barangay->id,
    ]);

    return WorkerProfile::factory()->create([
        'user_id'             => $user->id,
        'verification_status' => 'approved',
        'bio'                 => 'Licensed electrician.',
    ]);
}

function makeAuthClient(): User
{
    $barangay = Barangay::factory()->create();
    return User::factory()->create([
        'role'              => 'client',
        'email_verified_at' => now(),
        'barangay_id'       => $barangay->id,
    ]);
}

it('returns 200 with correct shape for an approved worker', function () {
    $client  = makeAuthClient();
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
                'reviews',
            ],
        ]);

    expect($response->json('data.worker_profile_id'))->toBe($profile->id);
    expect($response->json('data.portfolio_photos'))->toBe([]);
});

it('returns 404 for a non-existent worker_profile_id', function () {
    $client = makeAuthClient();

    $this->actingAs($client)->get('/api/workers/99999')
        ->assertStatus(404);
});

it('returns 404 when a client views an unapproved worker', function () {
    $client   = makeAuthClient();
    $barangay = Barangay::factory()->create();
    $user     = User::factory()->create(['role' => 'worker', 'email_verified_at' => now(), 'barangay_id' => $barangay->id]);
    $profile  = WorkerProfile::factory()->create(['user_id' => $user->id, 'verification_status' => 'pending']);

    $this->actingAs($client)->get("/api/workers/{$profile->id}")
        ->assertStatus(404);
});

it('returns 200 when an admin views an unapproved worker', function () {
    $barangay = Barangay::factory()->create();
    $admin    = User::factory()->create([
        'role'              => 'admin',
        'email_verified_at' => now(),
        'barangay_id'       => $barangay->id,
    ]);

    $workerBarangay = Barangay::factory()->create();
    $workerUser     = User::factory()->create(['role' => 'worker', 'email_verified_at' => now(), 'barangay_id' => $workerBarangay->id]);
    $profile        = WorkerProfile::factory()->create(['user_id' => $workerUser->id, 'verification_status' => 'pending']);

    $this->actingAs($admin)->get("/api/workers/{$profile->id}")
        ->assertStatus(200)
        ->assertJson(['success' => true]);
});

it('response includes reviews array with correct fields', function () {
    $client  = makeAuthClient();
    $profile = makeApprovedWorkerProfile();

    $rater   = User::factory()->create(['name' => 'Ana Cruz', 'email_verified_at' => now()]);
    $booking = Booking::factory()->create([
        'client_id' => $rater->id,
        'worker_id' => $profile->user_id,
        'status'    => 'completed',
    ]);

    RatingReview::factory()->create([
        'booking_id' => $booking->id,
        'rated_by'   => $rater->id,
        'rated_user' => $profile->user_id,
        'score'      => 5,
        'comment'    => 'Very professional!',
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

it('returns 401 for unauthenticated request', function () {
    $profile = makeApprovedWorkerProfile();

    $this->get("/api/workers/{$profile->id}")->assertStatus(401);
});
