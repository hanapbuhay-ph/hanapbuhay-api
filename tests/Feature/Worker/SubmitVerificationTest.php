<?php

use App\Models\AdminNotification;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Models\VerificationHistory;
use App\Models\WorkerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('private');
});

function makeWorker(): User
{
    $user = User::factory()->create([
        'role' => 'worker',
        'email_verified_at' => now(),
    ]);
    WorkerProfile::factory()->create(['user_id' => $user->id, 'verification_status' => 'unverified']);

    return $user;
}

function fakeImage(): UploadedFile
{
    return UploadedFile::fake()->image('doc.jpg', 100, 100);
}

it('allows worker to submit all required and optional documents', function () {
    $worker = makeWorker();

    $response = $this->actingAs($worker)->post('/api/worker/verification/submit', [
        'government_id_front' => fakeImage(),
        'government_id_back' => fakeImage(),
        'barangay_certificate' => fakeImage(),
        'selfie_with_id' => fakeImage(),
        'skill_certificate' => fakeImage(),
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Documents submitted for review.',
            'data' => ['verification_status' => 'pending'],
        ]);

    $notification = AdminNotification::where('type', 'verification_submitted')->firstOrFail();
    expect($notification->data['url'])->toBe("/verifications/{$worker->workerProfile->id}");
});

it('allows worker to submit all required documents without skill_certificate', function () {
    $worker = makeWorker();

    $response = $this->actingAs($worker)->post('/api/worker/verification/submit', [
        'government_id_front' => fakeImage(),
        'government_id_back' => fakeImage(),
        'barangay_certificate' => fakeImage(),
        'selfie_with_id' => fakeImage(),
    ]);

    $response->assertStatus(201)
        ->assertJson(['success' => true, 'data' => ['verification_status' => 'pending']]);
});

it('returns 403 for non-worker (client role)', function () {
    $client = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);

    $response = $this->actingAs($client)->post('/api/worker/verification/submit', [
        'government_id_front' => fakeImage(),
        'government_id_back' => fakeImage(),
        'barangay_certificate' => fakeImage(),
        'selfie_with_id' => fakeImage(),
    ]);

    $response->assertStatus(403)
        ->assertJson(['success' => false, 'message' => 'This action is for workers only.']);
});

it('returns 422 when verification is already pending', function () {
    $user = User::factory()->create(['role' => 'worker', 'email_verified_at' => now()]);
    WorkerProfile::factory()->create(['user_id' => $user->id, 'verification_status' => 'pending']);

    $response = $this->actingAs($user)->post('/api/worker/verification/submit', [
        'government_id_front' => fakeImage(),
        'government_id_back' => fakeImage(),
        'barangay_certificate' => fakeImage(),
        'selfie_with_id' => fakeImage(),
    ]);

    $response->assertStatus(422)
        ->assertJson(['success' => false, 'message' => 'You already have a pending or approved verification.']);
});

it('returns 422 when verification is already approved', function () {
    $user = User::factory()->create(['role' => 'worker', 'email_verified_at' => now()]);
    WorkerProfile::factory()->create(['user_id' => $user->id, 'verification_status' => 'approved']);

    $response = $this->actingAs($user)->post('/api/worker/verification/submit', [
        'government_id_front' => fakeImage(),
        'government_id_back' => fakeImage(),
        'barangay_certificate' => fakeImage(),
        'selfie_with_id' => fakeImage(),
    ]);

    $response->assertStatus(422)
        ->assertJson(['success' => false, 'message' => 'You already have a pending or approved verification.']);
});

it('returns 422 when the government ID front is missing', function () {
    $worker = makeWorker();

    $response = $this->actingAs($worker)->post('/api/worker/verification/submit', [
        'government_id_back' => fakeImage(),
        'barangay_certificate' => fakeImage(),
        'selfie_with_id' => fakeImage(),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['government_id_front']);
});

it('requires both sides of a government ID', function () {
    $worker = makeWorker();

    $response = $this->actingAs($worker)->post('/api/worker/verification/submit', [
        'government_id_front' => fakeImage(),
        'barangay_certificate' => fakeImage(),
        'selfie_with_id' => fakeImage(),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['government_id_back']);
});

it('creates verification_documents rows in the database', function () {
    $worker = makeWorker();

    $this->actingAs($worker)->post('/api/worker/verification/submit', [
        'government_id_front' => fakeImage(),
        'government_id_back' => fakeImage(),
        'barangay_certificate' => fakeImage(),
        'selfie_with_id' => fakeImage(),
    ]);

    $profileId = $worker->workerProfile->id;

    expect(VerificationDocument::where('worker_profile_id', $profileId)->count())->toBe(4);
    expect(VerificationDocument::where('worker_profile_id', $profileId)
        ->where('document_type', 'government_id_front')->exists())->toBeTrue();
    expect(VerificationDocument::where('worker_profile_id', $profileId)
        ->where('document_type', 'government_id_back')->exists())->toBeTrue();
    expect(VerificationHistory::where('worker_profile_id', $profileId)
        ->where('status', 'pending')->whereNull('changed_by')->exists())->toBeTrue();
});

it('sets worker_profiles verification_status to pending', function () {
    $worker = makeWorker();

    $this->actingAs($worker)->post('/api/worker/verification/submit', [
        'government_id_front' => fakeImage(),
        'government_id_back' => fakeImage(),
        'barangay_certificate' => fakeImage(),
        'selfie_with_id' => fakeImage(),
    ]);

    expect($worker->workerProfile->fresh()->verification_status)->toBe('pending');
});

it('returns both government ID sides in the admin verification detail', function () {
    $worker = makeWorker();

    $this->actingAs($worker)->post('/api/worker/verification/submit', [
        'government_id_front' => fakeImage('id-front.jpg'),
        'government_id_back' => fakeImage('id-back.jpg'),
        'barangay_certificate' => fakeImage('barangay.jpg'),
        'selfie_with_id' => fakeImage('selfie.jpg'),
    ])->assertCreated();

    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/verifications/{$worker->workerProfile->id}")
        ->assertOk();

    $documentsByType = collect($response->json('data.verification.documents'))
        ->keyBy('type');

    expect($documentsByType->has('government_id_front'))->toBeTrue()
        ->and($documentsByType->has('government_id_back'))->toBeTrue()
        ->and($documentsByType['government_id_front']['file_url'])
        ->toContain('/api/media/private?')
        ->and($documentsByType['government_id_back']['file_url'])
        ->toContain('signature=');
});
