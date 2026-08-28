<?php

use App\Models\Barangay;
use App\Models\Booking;
use App\Models\Report;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Models\WorkerProfile;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Local helpers ─────────────────────────────────────────────────────────────

$makeAdmin = function (): User {
    return User::factory()->create(['role' => 'admin', 'is_active' => true]);
};

// ══════════════════════════════════════════════════════════════════════════════
// MIDDLEWARE
// ══════════════════════════════════════════════════════════════════════════════

it('returns 403 when a non-admin (client) hits any admin route', function () use ($makeAdmin) {
    $client = makeBookingClient();

    $this->actingAs($client)
        ->getJson('/api/admin/dashboard')
        ->assertStatus(403)
        ->assertJsonPath('success', false);
});

it('returns 401 when unauthenticated', function () {
    // Sanctum returns {"message":"Unauthenticated."} — no custom success key.
    $this->getJson('/api/admin/dashboard')
        ->assertStatus(401)
        ->assertJsonPath('message', 'Unauthenticated.');
});

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 1 — VERIFICATIONS
// ══════════════════════════════════════════════════════════════════════════════

it('admin lists all verifications with pagination', function () use ($makeAdmin) {
    $admin = $makeAdmin();

    $worker = makeApprovedBookingWorker();

    $this->actingAs($admin)
        ->getJson('/api/admin/verifications')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Verifications retrieved.')
        ->assertJsonStructure([
            'data' => [
                'verifications' => [['id', 'user', 'verification_status', 'documents', 'updated_at']],
                'pagination'    => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ]);
});

it('admin filters verifications by status=pending and only pending records are returned', function () use ($makeAdmin) {
    $admin = $makeAdmin();

    // pending worker
    $pendingWorker = User::factory()->create(['role' => 'worker']);
    WorkerProfile::factory()->create([
        'user_id'             => $pendingWorker->id,
        'verification_status' => 'pending',
    ]);

    // approved worker
    makeApprovedBookingWorker();

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/verifications?status=pending')
        ->assertStatus(200);

    $statuses = collect($response->json('data.verifications'))->pluck('verification_status');
    expect($statuses->every(fn ($s) => $s === 'pending'))->toBeTrue();
});

it('admin approves a worker verification', function () use ($makeAdmin) {
    $admin = $makeAdmin();

    $worker = User::factory()->create(['role' => 'worker']);
    $profile = WorkerProfile::factory()->create([
        'user_id'             => $worker->id,
        'verification_status' => 'pending',
    ]);
    VerificationDocument::factory()->create([
        'worker_profile_id' => $profile->id,
        'status'            => 'pending',
    ]);

    // Mock NotificationService so no real FCM call is made
    $this->mock(NotificationService::class)
        ->shouldReceive('sendPush')
        ->once()
        ->withArgs(function (User $user, string $title) use ($worker): bool {
            return $user->id === $worker->id && $title === 'Verification Approved';
        });

    $this->actingAs($admin)
        ->postJson("/api/admin/verifications/{$profile->id}/review", [
            'action'      => 'approve',
            'admin_notes' => 'All documents verified.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Worker verification approved.')
        ->assertJsonPath('data.verification_status', 'approved');

    expect($profile->fresh()->verification_status)->toBe('approved');
    expect($profile->verificationDocuments()->where('status', 'approved')->exists())->toBeTrue();
});

it('admin rejects a worker verification', function () use ($makeAdmin) {
    $admin = $makeAdmin();

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
        ->withArgs(function (User $user, string $title) use ($worker): bool {
            return $user->id === $worker->id && $title === 'Verification Rejected';
        });

    $this->actingAs($admin)
        ->postJson("/api/admin/verifications/{$profile->id}/review", [
            'action' => 'reject',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.verification_status', 'rejected');

    expect($profile->fresh()->verification_status)->toBe('rejected');
});

it('returns 422 for an invalid action value in verification review', function () use ($makeAdmin) {
    $admin   = $makeAdmin();
    $profile = WorkerProfile::factory()->create();

    $this->actingAs($admin)
        ->postJson("/api/admin/verifications/{$profile->id}/review", [
            'action' => 'suspend',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 404 for a non-existent workerProfileId in verification review', function () use ($makeAdmin) {
    $admin = $makeAdmin();

    $this->actingAs($admin)
        ->postJson('/api/admin/verifications/99999/review', [
            'action' => 'approve',
        ])
        ->assertStatus(404);
});

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 2 — USER MANAGEMENT
// ══════════════════════════════════════════════════════════════════════════════

it('admin lists all users with pagination', function () use ($makeAdmin) {
    $admin = $makeAdmin();
    User::factory()->count(3)->create(['role' => 'client']);

    $this->actingAs($admin)
        ->getJson('/api/admin/users')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Users retrieved.')
        ->assertJsonStructure([
            'data' => [
                'users'      => [['id', 'name', 'email', 'role', 'is_active', 'created_at']],
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ]);
});

it('admin filters users by role=worker and only workers are returned', function () use ($makeAdmin) {
    $admin = $makeAdmin();
    makeApprovedBookingWorker();
    makeBookingClient();

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/users?role=worker')
        ->assertStatus(200);

    $roles = collect($response->json('data.users'))->pluck('role');
    expect($roles->every(fn ($r) => $r === 'worker'))->toBeTrue();
});

it('admin filters users by partial name search', function () use ($makeAdmin) {
    $admin = $makeAdmin();
    User::factory()->create(['name' => 'Pedro Penduko', 'role' => 'client']);
    User::factory()->create(['name' => 'Juan Dela Cruz', 'role' => 'client']);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/users?search=Pedro')
        ->assertStatus(200);

    $names = collect($response->json('data.users'))->pluck('name');
    expect($names->contains('Pedro Penduko'))->toBeTrue();
    expect($names->contains('Juan Dela Cruz'))->toBeFalse();
});

it('admin views a single worker user and response includes worker_profile', function () use ($makeAdmin) {
    $admin  = $makeAdmin();
    $worker = makeApprovedBookingWorker();

    $this->actingAs($admin)
        ->getJson("/api/admin/users/{$worker->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.role', 'worker')
        ->assertJsonStructure(['data' => ['worker_profile' => ['verification_status', 'average_rating', 'total_reviews']]]);
});

it('admin views a single client user and response has no worker_profile', function () use ($makeAdmin) {
    $admin  = $makeAdmin();
    $client = makeBookingClient();

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/users/{$client->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.role', 'client');

    expect($response->json('data'))->not->toHaveKey('worker_profile');
});

it('admin toggles a user inactive', function () use ($makeAdmin) {
    $admin  = $makeAdmin();
    $client = User::factory()->create(['role' => 'client', 'is_active' => true]);

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$client->id}/toggle-active")
        ->assertStatus(200)
        ->assertJsonPath('data.is_active', false);

    expect($client->fresh()->is_active)->toBeFalse();
});

it('admin toggles the same user active again', function () use ($makeAdmin) {
    $admin  = $makeAdmin();
    $client = User::factory()->create(['role' => 'client', 'is_active' => false]);

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$client->id}/toggle-active")
        ->assertStatus(200)
        ->assertJsonPath('data.is_active', true);

    expect($client->fresh()->is_active)->toBeTrue();
});

it('admin cannot deactivate their own account', function () use ($makeAdmin) {
    $admin = $makeAdmin();

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$admin->id}/toggle-active")
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'You cannot deactivate your own account.');
});

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 3 — BOOKING OVERSIGHT
// ══════════════════════════════════════════════════════════════════════════════

it('admin lists all bookings with pagination', function () use ($makeAdmin) {
    $admin    = $makeAdmin();
    $client   = makeBookingClient();
    $worker   = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => 'completed',
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/bookings')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Bookings retrieved.')
        ->assertJsonStructure([
            'data' => [
                'bookings'   => [['id', 'booking_code', 'status', 'client', 'worker', 'service_category']],
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ]);
});

it('admin filters bookings by status=completed and only completed bookings are returned', function () use ($makeAdmin) {
    $admin    = $makeAdmin();
    $client   = makeBookingClient();
    $worker   = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => 'completed',
    ]);
    Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => 'pending',
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/bookings?status=completed')
        ->assertStatus(200);

    $statuses = collect($response->json('data.bookings'))->pluck('status');
    expect($statuses->every(fn ($s) => $s === 'completed'))->toBeTrue();
});

it('admin views a single booking with full detail', function () use ($makeAdmin) {
    $admin    = $makeAdmin();
    $client   = makeBookingClient();
    $worker   = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    $booking = Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => 'completed',
        'notes'               => 'Please bring tools.',
    ]);

    $this->actingAs($admin)
        ->getJson("/api/admin/bookings/{$booking->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $booking->id)
        ->assertJsonPath('data.notes', 'Please bring tools.')
        ->assertJsonStructure(['data' => ['cancelled_by', 'cancellation_reason', 'started_at', 'completed_at']]);
});

it('returns 404 for a non-existent booking', function () use ($makeAdmin) {
    $admin = $makeAdmin();

    $this->actingAs($admin)
        ->getJson('/api/admin/bookings/99999')
        ->assertStatus(404);
});

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 4 — REPORT MANAGEMENT
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Helper: create a Report with linked booking, reporter, and reported user.
 */
$makeReport = function (string $status = 'under_review'): Report {
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
        'description'   => 'Worker was rude.',
        'evidence_paths'=> ['evidence/1.jpg'],
        'status'        => $status,
        'admin_remarks' => null,
    ]);
};

it('admin lists all reports with pagination', function () use ($makeAdmin, $makeReport) {
    $admin = $makeAdmin();
    $makeReport();

    $this->actingAs($admin)
        ->getJson('/api/admin/reports')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Reports retrieved.')
        ->assertJsonStructure([
            'data' => [
                'reports'    => [['id', 'reason', 'status', 'booking', 'reporter', 'reported_user']],
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ]);
});

it('admin filters reports by status=under_review and only correct results are returned', function () use ($makeAdmin, $makeReport) {
    $admin = $makeAdmin();
    $makeReport('under_review');
    $makeReport('resolved');

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/reports?status=under_review')
        ->assertStatus(200);

    $statuses = collect($response->json('data.reports'))->pluck('status');
    expect($statuses->every(fn ($s) => $s === 'under_review'))->toBeTrue();
});

it('admin views a single report with evidence_paths and admin_notes', function () use ($makeAdmin, $makeReport) {
    $admin  = $makeAdmin();
    $report = $makeReport();

    $this->actingAs($admin)
        ->getJson("/api/admin/reports/{$report->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $report->id)
        ->assertJsonStructure(['data' => ['evidence_paths', 'admin_notes', 'description']]);
});

it('admin resolves a report and status is resolved in DB', function () use ($makeAdmin, $makeReport) {
    $admin  = $makeAdmin();
    $report = $makeReport();

    $this->actingAs($admin)
        ->patchJson("/api/admin/reports/{$report->id}/resolve", [
            'status'      => 'resolved',
            'admin_notes' => 'Issue has been addressed.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Report updated.')
        ->assertJsonPath('data.status', 'resolved');

    expect($report->fresh()->status)->toBe('resolved');
    expect($report->fresh()->admin_remarks)->toBe('Issue has been addressed.');
});

it('admin dismisses a report and status is dismissed in DB', function () use ($makeAdmin, $makeReport) {
    $admin  = $makeAdmin();
    $report = $makeReport();

    $this->actingAs($admin)
        ->patchJson("/api/admin/reports/{$report->id}/resolve", [
            'status' => 'dismissed',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'dismissed');

    expect($report->fresh()->status)->toBe('dismissed');
});

it('returns 422 for an invalid status value in resolve report', function () use ($makeAdmin, $makeReport) {
    $admin  = $makeAdmin();
    $report = $makeReport();

    $this->actingAs($admin)
        ->patchJson("/api/admin/reports/{$report->id}/resolve", [
            'status' => 'archived',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 404 for a non-existent report', function () use ($makeAdmin) {
    $admin = $makeAdmin();

    $this->actingAs($admin)
        ->patchJson('/api/admin/reports/99999/resolve', [
            'status' => 'resolved',
        ])
        ->assertStatus(404);
});

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 5 — DASHBOARD STATS
// ══════════════════════════════════════════════════════════════════════════════

it('dashboard returns all stat keys with correct integer values', function () use ($makeAdmin) {
    $admin = $makeAdmin();

    // Seed known counts
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    // One pending verification
    $pendingWorker = User::factory()->create(['role' => 'worker']);
    WorkerProfile::factory()->create([
        'user_id'             => $pendingWorker->id,
        'verification_status' => 'pending',
    ]);

    // Bookings
    $activeBooking = Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => 'active',
    ]);
    $completedBooking = Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => 'completed',
    ]);

    // Report under review
    Report::create([
        'booking_id'    => $completedBooking->id,
        'reported_by'   => $client->id,
        'reported_user' => $worker->id,
        'reason'        => 'misconduct',
        'description'   => 'Test',
        'status'        => 'under_review',
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/dashboard')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Dashboard stats retrieved.')
        ->assertJsonStructure([
            'data' => [
                'total_users',
                'total_workers',
                'total_clients',
                'pending_verifications',
                'total_bookings',
                'active_bookings',
                'completed_bookings',
                'total_reports',
                'open_reports',
            ],
        ]);

    $data = $response->json('data');

    // All values must be integers (non-negative)
    foreach ($data as $key => $value) {
        expect($value)->toBeInt("Expected {$key} to be an integer");
    }

    // Spot-check specific counts against what we seeded
    // total_users: admin(1) + client(1) + approved-worker(1) + pending-worker(1) = 4
    expect($data['total_users'])->toBe(4);
    expect($data['pending_verifications'])->toBe(1);
    expect($data['active_bookings'])->toBe(1);
    expect($data['completed_bookings'])->toBe(1);
    expect($data['total_bookings'])->toBe(2);
    expect($data['total_reports'])->toBe(1);
    expect($data['open_reports'])->toBe(1);
});
