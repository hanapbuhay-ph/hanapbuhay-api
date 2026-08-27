<?php

use App\Models\User;
use App\Models\WorkerProfile;
use App\Models\VerificationDocument;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('returns 200 with verification status for a worker', function () {
    $user    = User::factory()->create(['role' => 'worker', 'email_verified_at' => now()]);
    $profile = WorkerProfile::factory()->create([
        'user_id'              => $user->id,
        'verification_status'  => 'rejected',
        'verification_remarks' => 'Barangay certificate is unclear.',
    ]);

    VerificationDocument::factory()->create([
        'worker_profile_id' => $profile->id,
        'document_type'     => 'government_id',
        'status'            => 'approved',
    ]);
    VerificationDocument::factory()->create([
        'worker_profile_id' => $profile->id,
        'document_type'     => 'barangay_certificate',
        'status'            => 'rejected',
    ]);

    $response = $this->actingAs($user)->get('/api/worker/verification/status');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data'    => [
                'verification_status' => 'rejected',
                'remarks'             => 'Barangay certificate is unclear.',
            ],
        ]);
});

it('returns 403 for non-worker (client role)', function () {
    $client = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);

    $this->actingAs($client)->get('/api/worker/verification/status')
        ->assertStatus(403)
        ->assertJson(['success' => false, 'message' => 'This action is for workers only.']);
});

it('response includes documents array with correct type and status fields', function () {
    $user    = User::factory()->create(['role' => 'worker', 'email_verified_at' => now()]);
    $profile = WorkerProfile::factory()->create(['user_id' => $user->id]);

    VerificationDocument::factory()->create([
        'worker_profile_id' => $profile->id,
        'document_type'     => 'selfie_with_id',
        'status'            => 'pending',
    ]);

    $response = $this->actingAs($user)->get('/api/worker/verification/status');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'verification_status',
                'trust_tier',
                'remarks',
                'documents' => [
                    '*' => ['type', 'status'],
                ],
            ],
        ]);

    $documents = $response->json('data.documents');
    expect($documents[0]['type'])->toBe('selfie_with_id');
    expect($documents[0]['status'])->toBe('pending');
});
