<?php

use App\Models\User;
use App\Models\WorkerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// ── helpers ───────────────────────────────────────────────────────────────────

function makeAdminForDeletion(): User
{
    return User::factory()->create(['role' => 'admin', 'is_active' => true]);
}

// ══════════════════════════════════════════════════════════════════════════════
// USER SIDE — POST /api/user/delete-account
// ══════════════════════════════════════════════════════════════════════════════

it('authenticated user can submit an account deletion request', function () {
    $user = makeBookingClient();

    $this->actingAs($user)
        ->postJson('/api/user/delete-account')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Account deletion requested. An admin will process your request within 30 days.');

    expect($user->fresh()->deletion_requested_at)->not->toBeNull();
});

it('submitting deletion request twice is idempotent', function () {
    $user = makeBookingClient();

    $this->actingAs($user)->postJson('/api/user/delete-account')->assertStatus(200);
    $firstTimestamp = $user->fresh()->deletion_requested_at;

    // Small delay won't matter — it won't update the timestamp
    $this->actingAs($user)->postJson('/api/user/delete-account')->assertStatus(200);

    expect($user->fresh()->deletion_requested_at->toDateTimeString())
        ->toBe($firstTimestamp->toDateTimeString());
});

it('returns 401 when unauthenticated on delete-account', function () {
    $this->postJson('/api/user/delete-account')->assertStatus(401);
});

// ══════════════════════════════════════════════════════════════════════════════
// USER SIDE — DELETE /api/user/delete-account  (cancel request)
// ══════════════════════════════════════════════════════════════════════════════

it('user can cancel a pending deletion request', function () {
    $user = makeBookingClient();
    $user->update(['deletion_requested_at' => now()]);

    $this->actingAs($user)
        ->deleteJson('/api/user/delete-account')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Deletion request cancelled.');

    expect($user->fresh()->deletion_requested_at)->toBeNull();
});

it('returns 422 when cancelling with no pending request', function () {
    $user = makeBookingClient(); // no deletion_requested_at

    $this->actingAs($user)
        ->deleteJson('/api/user/delete-account')
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 401 when unauthenticated on cancel delete-account', function () {
    $this->deleteJson('/api/user/delete-account')->assertStatus(401);
});

// ══════════════════════════════════════════════════════════════════════════════
// ADMIN SIDE — GET /api/admin/deletion-requests
// ══════════════════════════════════════════════════════════════════════════════

it('admin can list pending deletion requests', function () {
    $admin = makeAdminForDeletion();
    $user  = makeBookingClient();
    $user->update(['deletion_requested_at' => now()]);

    $this->actingAs($admin)
        ->getJson('/api/admin/deletion-requests')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Deletion requests retrieved.')
        ->assertJsonStructure([
            'data' => [
                'users'      => [['id', 'name', 'email', 'role', 'deletion_requested_at']],
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ]);
});

it('deletion requests list only shows users with pending requests', function () {
    $admin     = makeAdminForDeletion();
    $requester = makeBookingClient();
    $normal    = makeBookingClient();

    $requester->update(['deletion_requested_at' => now()]);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/deletion-requests')
        ->assertStatus(200);

    $ids = collect($response->json('data.users'))->pluck('id');
    expect($ids)->toContain($requester->id);
    expect($ids)->not->toContain($normal->id);
});

it('returns empty list when no deletion requests exist', function () {
    $admin = makeAdminForDeletion();

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/deletion-requests')
        ->assertStatus(200);

    expect($response->json('data.users'))->toBeEmpty();
});

it('returns 403 for non-admin on deletion requests list', function () {
    $client = makeBookingClient();

    $this->actingAs($client)
        ->getJson('/api/admin/deletion-requests')
        ->assertStatus(403);
});

// ══════════════════════════════════════════════════════════════════════════════
// ADMIN SIDE — POST /api/admin/deletion-requests/{id}/process
// ══════════════════════════════════════════════════════════════════════════════

it('admin can process an account deletion request', function () {
    $admin = makeAdminForDeletion();
    $user  = makeBookingClient();
    $user->update(['deletion_requested_at' => now()]);

    $this->actingAs($admin)
        ->postJson("/api/admin/deletion-requests/{$user->id}/process")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Account has been deleted and personal data anonymised.');

    // User should be soft-deleted
    expect(User::find($user->id))->toBeNull();
    expect(User::withTrashed()->find($user->id))->not->toBeNull();
});

it('processed account has anonymised PII', function () {
    $admin = makeAdminForDeletion();
    $user  = makeBookingClient();
    $user->update([
        'deletion_requested_at' => now(),
        'mobile_number'         => '09123456789',
    ]);

    $this->actingAs($admin)
        ->postJson("/api/admin/deletion-requests/{$user->id}/process");

    $deleted = User::withTrashed()->find($user->id);
    expect($deleted->name)->toBe('Deleted User');
    expect($deleted->email)->toBe("deleted_{$user->id}@deleted.hanapbuhay");
    expect($deleted->mobile_number)->toBeNull();
});

it('processing writes an admin audit log entry', function () {
    $admin = makeAdminForDeletion();
    $user  = makeBookingClient();
    $user->update(['deletion_requested_at' => now()]);

    $this->actingAs($admin)
        ->postJson("/api/admin/deletion-requests/{$user->id}/process");

    $this->assertDatabaseHas('admin_audit_logs', [
        'admin_id'    => $admin->id,
        'action'      => 'process_account_deletion',
        'target_type' => 'User',
        'target_id'   => $user->id,
    ]);
});

it('returns 404 when processing a user with no deletion request', function () {
    $admin = makeAdminForDeletion();
    $user  = makeBookingClient(); // no deletion_requested_at

    $this->actingAs($admin)
        ->postJson("/api/admin/deletion-requests/{$user->id}/process")
        ->assertStatus(404);
});

it('returns 404 for a non-existent user', function () {
    $admin = makeAdminForDeletion();

    $this->actingAs($admin)
        ->postJson('/api/admin/deletion-requests/99999/process')
        ->assertStatus(404);
});

it('returns 422 when processing an already-deleted account', function () {
    $admin = makeAdminForDeletion();
    $user  = makeBookingClient();
    $user->update(['deletion_requested_at' => now()]);
    $user->delete(); // already soft-deleted

    $this->actingAs($admin)
        ->postJson("/api/admin/deletion-requests/{$user->id}/process")
        ->assertStatus(422);
});

it('returns 403 for non-admin on process deletion', function () {
    $client  = makeBookingClient();
    $target  = makeBookingClient();
    $target->update(['deletion_requested_at' => now()]);

    $this->actingAs($client)
        ->postJson("/api/admin/deletion-requests/{$target->id}/process")
        ->assertStatus(403);
});
