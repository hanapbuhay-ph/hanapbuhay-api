<?php

use App\Models\Booking;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeGap5Admin(): User
{
    return User::factory()->create(['role' => 'admin', 'is_active' => true]);
}

// ══════════════════════════════════════════════════════════════════════════════
// GAP 5 — GET /api/admin/verifications/pending  (spec §K2 named sub-path)
// ══════════════════════════════════════════════════════════════════════════════

it('GET /admin/verifications/pending returns only pending verifications', function () {
    $admin = makeGap5Admin();

    $pendingWorker = User::factory()->create(['role' => 'worker']);
    WorkerProfile::factory()->create([
        'user_id'             => $pendingWorker->id,
        'verification_status' => 'pending',
    ]);

    $approvedWorker = makeApprovedBookingWorker();

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/verifications/pending')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Pending verifications retrieved.');

    $statuses = collect($response->json('data.verifications'))->pluck('verification_status');
    expect($statuses->every(fn ($s) => $s === 'pending'))->toBeTrue();
    expect($statuses)->toContain('pending');
});

it('/pending route returns same structure as /verifications?status=pending', function () {
    $admin = makeGap5Admin();

    $worker = User::factory()->create(['role' => 'worker']);
    WorkerProfile::factory()->create([
        'user_id'             => $worker->id,
        'verification_status' => 'pending',
    ]);

    $viaAlias = $this->actingAs($admin)
        ->getJson('/api/admin/verifications/pending')
        ->assertStatus(200);

    $viaQuery = $this->actingAs($admin)
        ->getJson('/api/admin/verifications?status=pending')
        ->assertStatus(200);

    // Both should return the same count
    expect($viaAlias->json('data.pagination.total'))
        ->toBe($viaQuery->json('data.pagination.total'));
});

it('/pending returns empty when no pending verifications exist', function () {
    $admin = makeGap5Admin();
    makeApprovedBookingWorker(); // approved, not pending

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/verifications/pending')
        ->assertStatus(200);

    expect($response->json('data.verifications'))->toBeEmpty();
});

it('/admin/verifications/pending returns 403 for non-admin', function () {
    $client = makeBookingClient();

    $this->actingAs($client)
        ->getJson('/api/admin/verifications/pending')
        ->assertStatus(403);
});

it('/admin/verifications/pending returns 401 when unauthenticated', function () {
    $this->getJson('/api/admin/verifications/pending')->assertStatus(401);
});

// ══════════════════════════════════════════════════════════════════════════════
// GAP 6 — Booking unverified worker is allowed (spec says it's a UI-only warning)
// ══════════════════════════════════════════════════════════════════════════════

it('client can book an unverified worker (pending verification)', function () {
    $client   = makeBookingClient();
    $category = ServiceCategory::factory()->create();

    $worker = User::factory()->create(['role' => 'worker']);
    WorkerProfile::factory()->create([
        'user_id'             => $worker->id,
        'verification_status' => 'pending',
    ]);

    $this->actingAs($client)
        ->postJson('/api/bookings', [
            'worker_id'           => $worker->id,
            'service_category_id' => $category->id,
            'scheduled_at'        => now()->addDays(2)->toDateTimeString(),
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    expect(Booking::where('client_id', $client->id)->where('worker_id', $worker->id)->exists())->toBeTrue();
});

it('client can book an unverified worker (unverified status)', function () {
    $client   = makeBookingClient();
    $category = ServiceCategory::factory()->create();

    $worker = User::factory()->create(['role' => 'worker']);
    WorkerProfile::factory()->create([
        'user_id'             => $worker->id,
        'verification_status' => 'unverified',
    ]);

    $this->actingAs($client)
        ->postJson('/api/bookings', [
            'worker_id'           => $worker->id,
            'service_category_id' => $category->id,
            'scheduled_at'        => now()->addDays(2)->toDateTimeString(),
        ])
        ->assertStatus(201);
});

it('booking a non-existent worker still returns 422', function () {
    $client   = makeBookingClient();
    $category = ServiceCategory::factory()->create();

    $this->actingAs($client)
        ->postJson('/api/bookings', [
            'worker_id'           => 99999,
            'service_category_id' => $category->id,
            'scheduled_at'        => now()->addDays(2)->toDateTimeString(),
        ])
        ->assertStatus(422);
});
