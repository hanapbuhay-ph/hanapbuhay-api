<?php

use App\Models\Booking;
use App\Models\JobPost;
use App\Models\Report;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Models\WorkerProfile;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeRemainingAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'is_active' => true]);
}

function makeRemainingReport(string $reason = 'misconduct'): Report
{
    $client   = makeBookingClient();
    $worker   = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    $booking = Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => 'completed',
    ]);

    return Report::create([
        'booking_id'    => $booking->id,
        'reported_by'   => $client->id,
        'reported_user' => $worker->id,
        'reason'        => $reason,
        'description'   => 'Test report.',
        'status'        => 'under_review',
    ]);
}

// ══════════════════════════════════════════════════════════════════════════════
// FIX 1 — Dashboard: spec fields total_active_job_posts, open_disputes, etc.
// ══════════════════════════════════════════════════════════════════════════════

it('dashboard response includes all spec-defined fields', function () {
    $admin = makeRemainingAdmin();

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/dashboard')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'total_users', 'total_workers', 'total_clients',
                'pending_verifications',
                'total_bookings', 'active_bookings', 'completed_bookings',
                'completed_bookings_today',
                'total_reports', 'open_reports', 'open_disputes',
                'total_active_job_posts',
                'recent_activity',
            ],
        ]);

    // All numeric fields should be non-negative integers
    $data = $response->json('data');
    foreach (['total_users', 'total_active_job_posts', 'open_disputes', 'completed_bookings_today'] as $key) {
        expect($data[$key])->toBeInt();
    }
});

it('dashboard total_active_job_posts counts active non-deleted posts', function () {
    $admin   = makeRemainingAdmin();
    $worker  = makeApprovedBookingWorker();
    $profile = $worker->workerProfile;
    $cat     = ServiceCategory::factory()->create();

    JobPost::factory()->count(3)->create([
        'worker_profile_id'   => $profile->id,
        'service_category_id' => $cat->id,
        'is_active'           => true,
    ]);

    // Soft-deleted one should NOT count
    $deleted = JobPost::factory()->create([
        'worker_profile_id'   => $profile->id,
        'service_category_id' => $cat->id,
        'is_active'           => true,
    ]);
    $deleted->delete();

    $response = $this->actingAs($admin)->getJson('/api/admin/dashboard')->assertStatus(200);

    expect($response->json('data.total_active_job_posts'))->toBe(3);
});

// ══════════════════════════════════════════════════════════════════════════════
// FIX 2 — Verification approval auto-sets trust_tier = verified
// ══════════════════════════════════════════════════════════════════════════════

it('approving a verification automatically sets trust_tier to verified', function () {
    $admin   = makeRemainingAdmin();
    $worker  = User::factory()->create(['role' => 'worker']);
    $profile = WorkerProfile::factory()->create([
        'user_id'             => $worker->id,
        'verification_status' => 'pending',
        'trust_tier'          => null,
    ]);
    VerificationDocument::factory()->create([
        'worker_profile_id' => $profile->id,
        'status'            => 'pending',
    ]);

    $this->mock(NotificationService::class)
        ->shouldReceive('sendPush')->once();

    $this->actingAs($admin)
        ->postJson("/api/admin/verifications/{$profile->id}/review", [
            'action'      => 'approve',
            'admin_notes' => 'All good.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.verification_status', 'approved');

    expect($profile->fresh()->trust_tier)->toBe('verified');
    expect($profile->fresh()->verification_status)->toBe('approved');
    expect($profile->fresh()->verified_at)->not->toBeNull();
});

it('rejecting a verification does NOT set trust_tier', function () {
    $admin   = makeRemainingAdmin();
    $worker  = User::factory()->create(['role' => 'worker']);
    $profile = WorkerProfile::factory()->create([
        'user_id'             => $worker->id,
        'verification_status' => 'pending',
        'trust_tier'          => null,
    ]);
    VerificationDocument::factory()->create([
        'worker_profile_id' => $profile->id,
        'status'            => 'pending',
    ]);

    $this->mock(NotificationService::class)->shouldReceive('sendPush')->once();

    $this->actingAs($admin)
        ->postJson("/api/admin/verifications/{$profile->id}/review", [
            'action' => 'reject',
        ]);

    expect($profile->fresh()->trust_tier)->toBeNull();
});

// ══════════════════════════════════════════════════════════════════════════════
// FIX 3 — GET /admin/reports filters by reason
// ══════════════════════════════════════════════════════════════════════════════

it('admin reports: filters by reason param', function () {
    $admin = makeRemainingAdmin();

    $r1 = makeRemainingReport('misconduct');
    $r2 = makeRemainingReport('no_show');

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/reports?reason=misconduct')
        ->assertStatus(200);

    $ids = collect($response->json('data.reports'))->pluck('id');
    expect($ids)->toContain($r1->id);
    expect($ids)->not->toContain($r2->id);
});

it('admin reports: all reports returned when no reason filter given', function () {
    $admin = makeRemainingAdmin();

    makeRemainingReport('misconduct');
    makeRemainingReport('no_show');

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/reports')
        ->assertStatus(200);

    expect($response->json('data.pagination.total'))->toBe(2);
});

// ══════════════════════════════════════════════════════════════════════════════
// FIX 4 — K13 Resolve Report using spec field resolution_action
// ══════════════════════════════════════════════════════════════════════════════

it('resolving a report with resolution_action=account_suspended suspends the user', function () {
    $admin  = makeRemainingAdmin();
    $report = makeRemainingReport();

    $reportedUser = User::find($report->reported_user);
    $reportedUser->update(['is_active' => true]);

    $this->actingAs($admin)
        ->patchJson("/api/admin/reports/{$report->id}/resolve", [
            'status'            => 'resolved',
            'resolution_action' => 'account_suspended',
            'admin_notes'       => 'Suspended per policy.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'resolved');

    expect($reportedUser->fresh()->is_active)->toBeFalse();
});

it('resolving a report with resolution_action=verification_revoked revokes trust tier', function () {
    $admin  = makeRemainingAdmin();
    $report = makeRemainingReport();

    $worker  = User::find($report->reported_user);
    $profile = $worker->workerProfile;
    $profile->update(['trust_tier' => 'verified']);

    $this->actingAs($admin)
        ->patchJson("/api/admin/reports/{$report->id}/resolve", [
            'status'            => 'resolved',
            'resolution_action' => 'verification_revoked',
        ])
        ->assertStatus(200);

    expect($profile->fresh()->trust_tier)->toBe('revoked');
});

it('resolving with resolution_action=warning_issued only updates status', function () {
    $admin  = makeRemainingAdmin();
    $report = makeRemainingReport();

    $reportedUser = User::find($report->reported_user);
    $reportedUser->update(['is_active' => true]);

    $this->actingAs($admin)
        ->patchJson("/api/admin/reports/{$report->id}/resolve", [
            'status'            => 'resolved',
            'resolution_action' => 'warning_issued',
            'admin_notes'       => 'Warning issued.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'resolved');

    expect($reportedUser->fresh()->is_active)->toBeTrue(); // not suspended
});

it('resolving with resolution_action=no_action just marks resolved with no side-effects', function () {
    $admin  = makeRemainingAdmin();
    $report = makeRemainingReport();

    $this->actingAs($admin)
        ->patchJson("/api/admin/reports/{$report->id}/resolve", [
            'status'            => 'resolved',
            'resolution_action' => 'no_action',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'resolved');
});

it('returns 422 for invalid resolution_action value', function () {
    $admin  = makeRemainingAdmin();
    $report = makeRemainingReport();

    $this->actingAs($admin)
        ->patchJson("/api/admin/reports/{$report->id}/resolve", [
            'status'            => 'resolved',
            'resolution_action' => 'vaporize_user',
        ])
        ->assertStatus(422);
});

it('legacy action field still works alongside new resolution_action', function () {
    $admin  = makeRemainingAdmin();
    $report = makeRemainingReport();

    $reportedUser = User::find($report->reported_user);
    $reportedUser->update(['is_active' => true]);

    $this->actingAs($admin)
        ->patchJson("/api/admin/reports/{$report->id}/resolve", [
            'status' => 'resolved',
            'action' => 'suspend_user',
        ])
        ->assertStatus(200);

    expect($reportedUser->fresh()->is_active)->toBeFalse();
});
