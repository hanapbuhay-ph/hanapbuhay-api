<?php

use App\Models\Booking;
use App\Models\HanapbuhayNotification;
use App\Models\Report;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Models\WorkerProfile;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Local helpers ─────────────────────────────────────────────────────────────

function makeAdminUser(): User
{
    return User::factory()->create(['role' => 'admin', 'is_active' => true]);
}

function makeReportWithStatus(string $status = 'under_review'): Report
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
        'reason'        => 'misconduct',
        'description'   => 'Test report.',
        'status'        => $status,
    ]);
}

// ══════════════════════════════════════════════════════════════════════════════
// FIX 1 — ADMIN USER SEEDER
// ══════════════════════════════════════════════════════════════════════════════

it('admin seeder creates the default admin account', function () {
    // Run only the AdminUserSeeder in isolation
    $seeder = new \Database\Seeders\AdminUserSeeder();
    $seeder->run();

    $admin = User::where('email', 'admin@hanapbuhay.com')->first();

    expect($admin)->not->toBeNull();
    expect($admin->role)->toBe('admin');
    expect($admin->is_active)->toBeTrue();
    expect($admin->email_verified_at)->not->toBeNull();
});

it('admin seeder is idempotent — running twice keeps one record', function () {
    $seeder = new \Database\Seeders\AdminUserSeeder();
    $seeder->run();
    $seeder->run();

    expect(User::where('email', 'admin@hanapbuhay.com')->count())->toBe(1);
});

it('admin seeder account can log in', function () {
    $seeder = new \Database\Seeders\AdminUserSeeder();
    $seeder->run();

    $this->postJson('/api/auth/login', [
        'email'    => 'admin@hanapbuhay.com',
        'password' => 'Admin@1234!',
    ])
    ->assertStatus(200)
    ->assertJsonPath('success', true)
    ->assertJsonPath('data.user.role', 'admin');
});

// ══════════════════════════════════════════════════════════════════════════════
// FIX 2 — request_resubmission ACTION IN VERIFICATION REVIEW
// ══════════════════════════════════════════════════════════════════════════════

it('admin can request resubmission for a pending verification', function () {
    $admin   = makeAdminUser();
    $worker  = User::factory()->create(['role' => 'worker']);
    $profile = WorkerProfile::factory()->create([
        'user_id'             => $worker->id,
        'verification_status' => 'pending',
    ]);
    VerificationDocument::factory()->create([
        'worker_profile_id' => $profile->id,
        'status'            => 'pending',
    ]);

    $this->mock(NotificationService::class)
        ->shouldReceive('sendPush')
        ->once()
        ->withArgs(fn ($user, $title) => $user->id === $worker->id && $title === 'Resubmission Required');

    $this->actingAs($admin)
        ->postJson("/api/admin/verifications/{$profile->id}/review", [
            'action'      => 'request_resubmission',
            'admin_notes' => 'Please provide clearer selfie with ID.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.verification_status', 'resubmission_required');

    expect($profile->fresh()->verification_status)->toBe('resubmission_required');
});

it('returns 422 for invalid action value in verification review', function () {
    $admin   = makeAdminUser();
    $profile = WorkerProfile::factory()->create();

    $this->actingAs($admin)
        ->postJson("/api/admin/verifications/{$profile->id}/review", [
            'action' => 'archive',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

// ══════════════════════════════════════════════════════════════════════════════
// FIX 3 — RESOLVE REPORT SIDE-EFFECTS
// ══════════════════════════════════════════════════════════════════════════════

it('resolving a report with suspend_user sets is_active=false on reported user', function () {
    $admin  = makeAdminUser();
    $report = makeReportWithStatus('under_review');

    $reportedUser = User::find($report->reported_user);
    $reportedUser->update(['is_active' => true]);

    $this->actingAs($admin)
        ->patchJson("/api/admin/reports/{$report->id}/resolve", [
            'status' => 'resolved',
            'action' => 'suspend_user',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'resolved');

    expect($reportedUser->fresh()->is_active)->toBeFalse();
});

it('resolving a report with revoke_trust_tier sets trust_tier=revoked on worker profile', function () {
    $admin  = makeAdminUser();
    $report = makeReportWithStatus('under_review');

    $worker  = User::find($report->reported_user);
    $profile = $worker->workerProfile;
    $profile->update(['trust_tier' => 'verified']);

    $this->actingAs($admin)
        ->patchJson("/api/admin/reports/{$report->id}/resolve", [
            'status' => 'resolved',
            'action' => 'revoke_trust_tier',
        ])
        ->assertStatus(200);

    expect($profile->fresh()->trust_tier)->toBe('revoked');
});

it('resolving without an action just updates the status', function () {
    $admin  = makeAdminUser();
    $report = makeReportWithStatus('under_review');

    $reportedUser = User::find($report->reported_user);
    $reportedUser->update(['is_active' => true]);

    $this->actingAs($admin)
        ->patchJson("/api/admin/reports/{$report->id}/resolve", [
            'status' => 'dismissed',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'dismissed');

    // No side-effects applied
    expect($reportedUser->fresh()->is_active)->toBeTrue();
});

it('returns 422 for invalid action value on resolve', function () {
    $admin  = makeAdminUser();
    $report = makeReportWithStatus('under_review');

    $this->actingAs($admin)
        ->patchJson("/api/admin/reports/{$report->id}/resolve", [
            'status' => 'resolved',
            'action' => 'delete_everything',
        ])
        ->assertStatus(422);
});

// ══════════════════════════════════════════════════════════════════════════════
// FIX 4 — IN-APP NOTIFICATIONS WIRED INTO BOOKING EVENTS
// ══════════════════════════════════════════════════════════════════════════════

it('accepting a booking creates an in-app notification for the client', function () {
    $client   = makeBookingClient();
    $worker   = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    $booking = Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => 'pending',
    ]);

    $this->mock(NotificationService::class)
        ->shouldReceive('notify')->once()
        ->shouldReceive('sendPush')->once();

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/accept")
        ->assertStatus(200);
});

it('declining a booking creates an in-app notification for the client', function () {
    $client   = makeBookingClient();
    $worker   = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    $booking = Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => 'pending',
    ]);

    $this->mock(NotificationService::class)
        ->shouldReceive('notify')->once()
        ->shouldReceive('sendPush')->once();

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/decline")
        ->assertStatus(200);
});

it('completing a booking creates an in-app notification for the client', function () {
    $client   = makeBookingClient();
    $worker   = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    $booking = Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => 'active',
    ]);

    $this->mock(NotificationService::class)
        ->shouldReceive('notify')->once()
        ->shouldReceive('sendPush')->once();

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/complete")
        ->assertStatus(200);
});

it('rating a booking creates an in-app notification for the rated worker', function () {
    $client   = makeBookingClient();
    $worker   = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    $booking = Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => 'completed',
    ]);

    $this->mock(NotificationService::class)
        ->shouldReceive('notify')->once()
        ->shouldReceive('sendPush')->once();

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/rate", ['score' => 5])
        ->assertStatus(201);
});
