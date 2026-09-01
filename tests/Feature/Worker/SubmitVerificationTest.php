<?php

use App\Models\User;
use App\Models\WorkerProfile;
use App\Models\VerificationDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

function makeWorker(): User
{
    $user = User::factory()->create([
        'role'              => 'worker',
        'email_verified_at' => now(),
    ]);
    WorkerProfile::factory()->create(['user_id' => $user->id, 'verification_status' => 'unverified']);
    return $user;
}

function fakeImage(): UploadedFile
{
    return UploadedFile::fake()->image('doc.jpg', 100, 100);
}

it('allows worker to submit all 4 documents', function () {
    $worker = makeWorker();

    $response = $this->actingAs($worker)->post('/api/worker/verification/submit', [
        'government_id'        => fakeImage(),
        'barangay_certificate' => fakeImage(),
        'selfie_with_id'       => fakeImage(),
        'skill_certificate'    => fakeImage(),
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Documents submitted for review.',
            'data'    => ['verification_status' => 'pending'],
        ]);
});

it('allows worker to submit 3 required documents without skill_certificate', function () {
    $worker = makeWorker();

    $response = $this->actingAs($worker)->post('/api/worker/verification/submit', [
        'government_id'        => fakeImage(),
        'barangay_certificate' => fakeImage(),
        'selfie_with_id'       => fakeImage(),
    ]);

    $response->assertStatus(201)
        ->assertJson(['success' => true, 'data' => ['verification_status' => 'pending']]);
});

it('returns 403 for non-worker (client role)', function () {
    $client = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);

    $response = $this->actingAs($client)->post('/api/worker/verification/submit', [
        'government_id'        => fakeImage(),
        'barangay_certificate' => fakeImage(),
        'selfie_with_id'       => fakeImage(),
    ]);

    $response->assertStatus(403)
        ->assertJson(['success' => false, 'message' => 'This action is for workers only.']);
});

it('returns 422 when verification is already pending', function () {
    $user    = User::factory()->create(['role' => 'worker', 'email_verified_at' => now()]);
    WorkerProfile::factory()->create(['user_id' => $user->id, 'verification_status' => 'pending']);

    $response = $this->actingAs($user)->post('/api/worker/verification/submit', [
        'government_id'        => fakeImage(),
        'barangay_certificate' => fakeImage(),
        'selfie_with_id'       => fakeImage(),
    ]);

    $response->assertStatus(422)
        ->assertJson(['success' => false, 'message' => 'You already have a pending or approved verification.']);
});

it('returns 422 when verification is already approved', function () {
    $user    = User::factory()->create(['role' => 'worker', 'email_verified_at' => now()]);
    WorkerProfile::factory()->create(['user_id' => $user->id, 'verification_status' => 'approved']);

    $response = $this->actingAs($user)->post('/api/worker/verification/submit', [
        'government_id'        => fakeImage(),
        'barangay_certificate' => fakeImage(),
        'selfie_with_id'       => fakeImage(),
    ]);

    $response->assertStatus(422)
        ->assertJson(['success' => false, 'message' => 'You already have a pending or approved verification.']);
});

it('returns 422 when required file government_id is missing', function () {
    $worker = makeWorker();

    $response = $this->actingAs($worker)->post('/api/worker/verification/submit', [
        'barangay_certificate' => fakeImage(),
        'selfie_with_id'       => fakeImage(),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['government_id']);
});

it('creates verification_documents rows in the database', function () {
    $worker = makeWorker();

    $this->actingAs($worker)->post('/api/worker/verification/submit', [
        'government_id'        => fakeImage(),
        'barangay_certificate' => fakeImage(),
        'selfie_with_id'       => fakeImage(),
    ]);

    $profileId = $worker->workerProfile->id;

    expect(VerificationDocument::where('worker_profile_id', $profileId)->count())->toBe(3);
    expect(VerificationDocument::where('worker_profile_id', $profileId)
        ->where('document_type', 'government_id')->exists())->toBeTrue();
});

it('sets worker_profiles verification_status to pending', function () {
    $worker = makeWorker();

    $this->actingAs($worker)->post('/api/worker/verification/submit', [
        'government_id'        => fakeImage(),
        'barangay_certificate' => fakeImage(),
        'selfie_with_id'       => fakeImage(),
    ]);

    expect($worker->workerProfile->fresh()->verification_status)->toBe('pending');
});
