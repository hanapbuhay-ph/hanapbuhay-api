<?php

use App\Models\AdminAuditLog;
use App\Models\Barangay;
use App\Models\Booking;
use App\Models\JobPost;
use App\Models\RatingReview;
use App\Models\Report;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Models\VerificationHistory;
use App\Models\WorkerProfile;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ── Local helpers ─────────────────────────────────────────────────────────────

$makeAdmin = function (): User {
    return User::factory()->create(['role' => 'admin', 'is_active' => true]);
};

// ══════════════════════════════════════════════════════════════════════════════
// MIDDLEWARE
// ══════════════════════════════════════════════════════════════════════════════

it('returns 403 when a non-admin (client) hits any admin route', function () {
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
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ]);
});

it('admin can retrieve a verification detail with document URLs', function () use ($makeAdmin) {
    $admin = $makeAdmin();
    $worker = User::factory()->create(['role' => 'worker']);
    $profile = WorkerProfile::factory()->create([
        'user_id' => $worker->id,
        'verification_status' => 'pending',
    ]);
    VerificationDocument::factory()->create([
        'worker_profile_id' => $profile->id,
        'document_type' => 'government_id',
        'file_path' => 'verifications/1/government_id.jpg',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/verifications/{$profile->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.verification.worker_profile_id', $profile->id)
        ->assertJsonPath('data.verification.documents.0.id', $profile->verificationDocuments->first()->id)
        ->assertJsonPath('data.verification.documents.0.type', 'government_id');

    expect($response->json('data.verification.documents.0.file_url'))
        ->toContain('/api/media/private?')
        ->toContain('signature=');
});

it('admin filters verifications by status=pending and only pending records are returned', function () use ($makeAdmin) {
    $admin = $makeAdmin();

    // pending worker
    $pendingWorker = User::factory()->create(['role' => 'worker']);
    WorkerProfile::factory()->create([
        'user_id' => $pendingWorker->id,
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
        'user_id' => $worker->id,
        'verification_status' => 'pending',
    ]);
    VerificationDocument::factory()->create([
        'worker_profile_id' => $profile->id,
        'status' => 'pending',
    ]);

    // Mock NotificationService so no real FCM call is made
    $this->mock(NotificationService::class)
        ->shouldReceive('notify')
        ->once()
        ->shouldReceive('sendPush')
        ->once()
        ->withArgs(function (User $user, string $title) use ($worker): bool {
            return $user->id === $worker->id && $title === 'Verification Approved';
        });

    $this->actingAs($admin)
        ->postJson("/api/admin/verifications/{$profile->id}/review", [
            'action' => 'approve',
            'admin_notes' => 'All documents verified.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Worker verification approved.')
        ->assertJsonPath('data.verification_status', 'approved');

    expect($profile->fresh()->verification_status)->toBe('approved');
    expect($profile->verificationDocuments()->where('status', 'approved')->exists())->toBeTrue();
    expect(VerificationHistory::where('worker_profile_id', $profile->id)
        ->where('status', 'approved')->where('changed_by', $admin->id)->exists())->toBeTrue();
    expect(AdminAuditLog::where('action', 'review_verification')
        ->where('target_id', $profile->id)
        ->where('outcome', 'success')
        ->where('ip_address', request()->ip())
        ->whereJsonContains('details->previous_status', 'pending')
        ->whereJsonContains('details->new_status', 'approved')
        ->exists())->toBeTrue();
});

it('admin rejects a worker verification', function () use ($makeAdmin) {
    $admin = $makeAdmin();

    $worker = User::factory()->create(['role' => 'worker']);
    $profile = WorkerProfile::factory()->create([
        'user_id' => $worker->id,
        'verification_status' => 'pending',
    ]);
    VerificationDocument::factory()->create([
        'worker_profile_id' => $profile->id,
        'status' => 'pending',
    ]);

    $this->mock(NotificationService::class)
        ->shouldReceive('notify')
        ->once()
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
    $admin = $makeAdmin();
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
                'users' => [['id', 'name', 'email', 'role', 'is_active', 'created_at']],
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ]);
});

it('super admin can access admin routes', function () {
    $superAdmin = User::factory()->create([
        'role' => 'super_admin',
        'email' => User::SUPER_ADMIN_EMAIL,
    ]);

    $this->actingAs($superAdmin)
        ->getJson('/api/admin/users')
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('ordinary admins cannot update, suspend, or delete the super admin account', function () use ($makeAdmin) {
    $admin = $makeAdmin();
    $superAdmin = User::factory()->create([
        'role' => 'super_admin',
        'email' => User::SUPER_ADMIN_EMAIL,
        'name' => 'Protected Super Admin',
        'password' => 'OriginalPassword123',
    ]);

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$superAdmin->id}", [
            'name' => 'Changed by Admin',
            'password' => 'ChangedPassword123',
            'password_confirmation' => 'ChangedPassword123',
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->postJson("/api/admin/users/{$superAdmin->id}/toggle-status", [
            'action' => 'suspend',
            'reason' => 'Testing super admin protection.',
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$superAdmin->id}/toggle-active")
        ->assertForbidden();

    $this->actingAs($admin)
        ->deleteJson("/api/admin/users/{$superAdmin->id}")
        ->assertForbidden();

    expect(AdminAuditLog::where('action', 'protected_account_change_denied')->count())->toBe(3)
        ->and(AdminAuditLog::where('action', 'protected_account_status_change_denied')->count())->toBe(1);

    $superAdmin->refresh();
    expect($superAdmin->name)->toBe('Protected Super Admin')
        ->and($superAdmin->is_active)->toBeTrue()
        ->and(Hash::check('OriginalPassword123', $superAdmin->password))->toBeTrue();
});

it('admins cannot assign the super admin role through user management', function () use ($makeAdmin) {
    $admin = $makeAdmin();
    $user = User::factory()->create(['role' => 'client']);

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$user->id}", ['role' => 'super_admin'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);

    $this->actingAs($admin)
        ->postJson('/api/admin/users', [
            'name' => 'Unauthorized Super Admin',
            'email' => 'unauthorized-super-admin@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'mobile_number' => '09171234567',
            'role' => 'super_admin',
            'barangay_id' => Barangay::factory()->create()->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});

it('admins cannot promote a client or worker to admin through user management', function () use ($makeAdmin) {
    $admin = $makeAdmin();

    foreach (['client', 'worker'] as $role) {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$user->id}", ['role' => 'admin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        expect($user->fresh()->role)->toBe($role);
    }
});

it('super admin can update its own account credentials', function () {
    $superAdmin = User::factory()->create([
        'role' => 'super_admin',
        'email' => User::SUPER_ADMIN_EMAIL,
    ]);

    $this->actingAs($superAdmin)
        ->patchJson("/api/admin/users/{$superAdmin->id}", [
            'name' => 'Updated Super Admin',
            'password' => 'UpdatedSuperPassword123',
            'password_confirmation' => 'UpdatedSuperPassword123',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Super Admin');

    expect(Hash::check('UpdatedSuperPassword123', $superAdmin->fresh()->password))->toBeTrue();
});

it('deletion processing cannot remove the super admin account', function () use ($makeAdmin) {
    $admin = $makeAdmin();
    $superAdmin = User::factory()->create([
        'role' => 'super_admin',
        'email' => User::SUPER_ADMIN_EMAIL,
        'deletion_requested_at' => now(),
    ]);

    $this->actingAs($admin)
        ->postJson("/api/admin/deletion-requests/{$superAdmin->id}/process")
        ->assertForbidden();

    expect(AdminAuditLog::where('action', 'protected_account_deletion_denied')->count())->toBe(1);

    expect($superAdmin->fresh())->not->toBeNull();
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
    $admin = $makeAdmin();
    $worker = makeApprovedBookingWorker();
    $profile = $worker->workerProfile;
    $profile->update([
        'bio' => 'Experienced home service worker.',
        'trust_tier' => 'trusted',
        'availability_status' => 'available',
        'completed_jobs' => 12,
        'verified_by' => $admin->id,
        'verified_at' => now(),
    ]);
    $category = ServiceCategory::factory()->create(['name' => 'Electrical']);
    $profile->serviceCategories()->attach($category->id);
    $post = JobPost::factory()->create([
        'worker_profile_id' => $profile->id,
        'service_category_id' => $category->id,
        'title' => 'Electrical repairs',
    ]);

    $this->actingAs($admin)
        ->getJson("/api/admin/users/{$worker->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.role', 'worker')
        ->assertJsonPath('data.worker_profile.bio', 'Experienced home service worker.')
        ->assertJsonPath('data.worker_profile.trust_tier', 'trusted')
        ->assertJsonPath('data.worker_profile.completed_jobs', 12)
        ->assertJsonPath('data.worker_profile.verified_by.id', $admin->id)
        ->assertJsonPath('data.worker_profile.service_categories.0.name', 'Electrical')
        ->assertJsonPath('data.worker_profile.job_posts.0.id', $post->id)
        ->assertJsonPath('data.worker_profile.job_posts.0.title', 'Electrical repairs')
        ->assertJsonStructure([
            'data' => [
                'worker_profile' => [
                    'verification_status', 'average_rating', 'total_reviews', 'bio',
                    'trust_tier', 'availability_status', 'completed_jobs',
                    'service_categories', 'job_posts',
                ],
                'activity' => [
                    'bookings' => ['total', 'by_status', 'recent'],
                    'reviews_received_count',
                    'reviews_given_count',
                ],
                'notification_preferences' => ['booking_updates', 'messages', 'promotions', 'push', 'email'],
            ],
        ]);
});

it('admin views client booking and review activity', function () use ($makeAdmin) {
    $admin = $makeAdmin();
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'worker_id' => $worker->id,
        'service_category_id' => $category->id,
        'status' => 'completed',
    ]);
    RatingReview::create([
        'booking_id' => $booking->id,
        'rated_by' => $worker->id,
        'rated_user' => $client->id,
        'score' => 5,
        'comment' => 'Excellent client.',
    ]);
    RatingReview::create([
        'booking_id' => $booking->id,
        'rated_by' => $client->id,
        'rated_user' => $worker->id,
        'score' => 4,
        'comment' => 'Professional worker.',
    ]);

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/users/{$client->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.role', 'client')
        ->assertJsonPath('data.activity.bookings.total', 1)
        ->assertJsonPath('data.activity.bookings.by_status.completed', 1)
        ->assertJsonPath('data.activity.bookings.recent.0.booking_code', $booking->booking_code)
        ->assertJsonPath('data.activity.reviews_received_count', 1)
        ->assertJsonPath('data.activity.recent_reviews_received.0.from', $worker->name)
        ->assertJsonPath('data.activity.reviews_given_count', 1)
        ->assertJsonPath('data.activity.recent_reviews_given.0.to', $worker->name);

    expect($response->json('data'))->not->toHaveKey('worker_profile');
});

it('admin can page through user bookings and reviews without loading all activity', function () use ($makeAdmin) {
    $admin = $makeAdmin();
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();

    foreach (range(1, 12) as $index) {
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'worker_id' => $worker->id,
        ]);
        RatingReview::create([
            'booking_id' => $booking->id,
            'rated_by' => $worker->id,
            'rated_user' => $client->id,
            'score' => 5,
            'comment' => "Received review {$index}.",
        ]);
        RatingReview::create([
            'booking_id' => $booking->id,
            'rated_by' => $client->id,
            'rated_user' => $worker->id,
            'score' => 4,
            'comment' => "Given review {$index}.",
        ]);
    }

    $detail = $this->actingAs($admin)
        ->getJson("/api/admin/users/{$client->id}")
        ->assertOk()
        ->assertJsonPath('data.activity.bookings.pagination.total', 12)
        ->assertJsonPath('data.activity.bookings.pagination.last_page', 2)
        ->assertJsonPath('data.activity.reviews_received_pagination.total', 12)
        ->assertJsonPath('data.activity.reviews_given_pagination.total', 12);

    expect($detail->json('data.activity.bookings.recent'))->toHaveCount(10)
        ->and($detail->json('data.activity.recent_reviews_received'))->toHaveCount(10)
        ->and($detail->json('data.activity.recent_reviews_given'))->toHaveCount(10);

    $this->getJson("/api/admin/users/{$client->id}/activity/bookings?page=2")
        ->assertOk()
        ->assertJsonPath('data.pagination.current_page', 2)
        ->assertJsonPath('data.pagination.total', 12)
        ->assertJsonCount(2, 'data.items');

    $this->getJson("/api/admin/users/{$client->id}/activity/reviews-received?page=2")
        ->assertOk()
        ->assertJsonPath('data.pagination.current_page', 2)
        ->assertJsonCount(2, 'data.items');

    $this->getJson("/api/admin/users/{$client->id}/activity/reviews-given?page=2")
        ->assertOk()
        ->assertJsonPath('data.pagination.current_page', 2)
        ->assertJsonCount(2, 'data.items');
});

it('admin can create a worker user and worker profile', function () use ($makeAdmin) {
    $admin = $makeAdmin();
    $barangay = Barangay::factory()->create();

    $response = $this->actingAs($admin)
        ->postJson('/api/admin/users', [
            'name' => 'New Worker',
            'email' => 'new-worker@example.com',
            'mobile_number' => '09171234567',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'role' => 'worker',
            'barangay_id' => $barangay->id,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.email', 'new-worker@example.com')
        ->assertJsonPath('data.mobile_number', '09171234567');

    $user = User::findOrFail($response->json('data.id'));
    expect($user->workerProfile)->not->toBeNull();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->mobile_number)->toBe('09171234567');
});

it('requires a barangay when admin creates a user', function () use ($makeAdmin) {
    $admin = $makeAdmin();

    $this->actingAs($admin)
        ->postJson('/api/admin/users', [
            'name' => 'Missing Barangay',
            'email' => 'missing-barangay@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'role' => 'client',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['barangay_id']);
});

it('admin can update and permanently delete a user and its owned data', function () use ($makeAdmin) {
    $admin = $makeAdmin();
    Storage::fake('public');

    $photoPath = 'profile-photos/user-avatar.jpg';
    $documentPath = 'verifications/worker-government-id.jpg';
    Storage::disk('public')->put($photoPath, 'avatar');
    Storage::disk('public')->put($documentPath, 'document');

    $user = User::factory()->create([
        'role' => 'worker',
        'profile_photo_path' => $photoPath,
    ]);
    $profile = WorkerProfile::factory()->create(['user_id' => $user->id]);
    $document = VerificationDocument::factory()->create([
        'worker_profile_id' => $profile->id,
        'file_path' => $documentPath,
    ]);
    $token = $user->createToken('existing-session');

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$user->id}", [
            'name' => 'Updated User',
            'is_active' => false,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated User');

    $this->actingAs($admin)
        ->deleteJson("/api/admin/users/{$user->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    expect(User::withTrashed()->find($user->id))->toBeNull();
    $this->assertDatabaseMissing('worker_profiles', ['id' => $profile->id]);
    $this->assertDatabaseMissing('verification_documents', ['id' => $document->id]);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    Storage::disk('public')->assertMissing($photoPath);
    Storage::disk('public')->assertMissing($documentPath);
});

it('admin can change a user password and receive a successful update response', function () use ($makeAdmin) {
    $admin = $makeAdmin();
    $user = User::factory()->create([
        'role' => 'client',
        'password' => 'OldPassword123',
    ]);

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$user->id}", [
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonMissingPath('data.password');

    expect(Hash::check('NewPassword123', $user->fresh()->password))->toBeTrue();
});

it('user update audit records outcome IP and only the changed field values', function () use ($makeAdmin) {
    $admin = $makeAdmin();
    $user = User::factory()->create([
        'role' => 'client',
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'password' => 'OldPassword123',
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25'])
        ->actingAs($admin)
        ->patchJson("/api/admin/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])
        ->assertOk();

    $log = AdminAuditLog::query()
        ->where('action', 'update_user')
        ->where('target_id', $user->id)
        ->firstOrFail();

    expect($log->category)->toBe('admin_activity')
        ->and($log->outcome)->toBe('success')
        ->and($log->ip_address)->toBe('203.0.113.25')
        ->and($log->details)->toMatchArray([
            'changes' => [
                'name' => ['from' => 'Original Name', 'to' => 'Updated Name'],
                'email' => ['from' => 'original@example.com', 'to' => 'updated@example.com'],
            ],
            'password_changed' => true,
        ])
        ->and(json_encode($log->details))->not->toContain('NewPassword123');
});

it('admin cannot delete their own account', function () use ($makeAdmin) {
    $admin = $makeAdmin();

    $this->actingAs($admin)
        ->deleteJson("/api/admin/users/{$admin->id}")
        ->assertStatus(422)
        ->assertJsonPath('message', 'You cannot delete your own account.');
});

it('admin toggles a user inactive', function () use ($makeAdmin) {
    $admin = $makeAdmin();
    $client = User::factory()->create(['role' => 'client', 'is_active' => true]);

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$client->id}/toggle-active")
        ->assertStatus(200)
        ->assertJsonPath('data.is_active', false);

    expect($client->fresh()->is_active)->toBeFalse();
});

it('admin toggles the same user active again', function () use ($makeAdmin) {
    $admin = $makeAdmin();
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
    $admin = $makeAdmin();
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    Booking::factory()->create([
        'client_id' => $client->id,
        'worker_id' => $worker->id,
        'service_category_id' => $category->id,
        'status' => 'completed',
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/bookings')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Bookings retrieved.')
        ->assertJsonStructure([
            'data' => [
                'bookings' => [['id', 'booking_code', 'status', 'client', 'worker', 'service_category']],
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ]);
});

it('admin filters bookings by status=completed and only completed bookings are returned', function () use ($makeAdmin) {
    $admin = $makeAdmin();
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    Booking::factory()->create([
        'client_id' => $client->id,
        'worker_id' => $worker->id,
        'service_category_id' => $category->id,
        'status' => 'completed',
    ]);
    Booking::factory()->create([
        'client_id' => $client->id,
        'worker_id' => $worker->id,
        'service_category_id' => $category->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/bookings?status=completed')
        ->assertStatus(200);

    $statuses = collect($response->json('data.bookings'))->pluck('status');
    expect($statuses->every(fn ($s) => $s === 'completed'))->toBeTrue();
});

it('admin views a single booking with full detail', function () use ($makeAdmin) {
    $admin = $makeAdmin();
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'worker_id' => $worker->id,
        'service_category_id' => $category->id,
        'status' => 'completed',
        'notes' => 'Please bring tools.',
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
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'worker_id' => $worker->id,
        'service_category_id' => $category->id,
        'status' => 'completed',
    ]);

    return Report::create([
        'booking_id' => $booking->id,
        'reported_by' => $client->id,
        'reported_user' => $worker->id,
        'reason' => 'misconduct',
        'description' => 'Worker was rude.',
        'evidence_paths' => ['evidence/1.jpg'],
        'status' => $status,
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
                'reports' => [['id', 'reason', 'status', 'booking', 'reporter', 'reported_user']],
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
    $admin = $makeAdmin();
    $report = $makeReport();

    $this->actingAs($admin)
        ->getJson("/api/admin/reports/{$report->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $report->id)
        ->assertJsonStructure(['data' => ['evidence_urls', 'admin_notes', 'description', 'resolution_action', 'resolved_by', 'resolved_at']]);
});

it('admin resolves a report and status is resolved in DB', function () use ($makeAdmin, $makeReport) {
    $admin = $makeAdmin();
    $report = $makeReport();

    $this->actingAs($admin)
        ->patchJson("/api/admin/reports/{$report->id}/resolve", [
            'status' => 'resolved',
            'admin_notes' => 'Issue has been addressed.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Report updated.')
        ->assertJsonPath('data.status', 'resolved');

    expect($report->fresh()->status)->toBe('resolved');
    expect($report->fresh()->admin_remarks)->toBe('Issue has been addressed.');
    expect(AdminAuditLog::where('action', 'resolve_report')
        ->where('target_id', $report->id)
        ->where('outcome', 'success')
        ->whereJsonContains('details->previous_status', $report->getOriginal('status'))
        ->whereJsonContains('details->new_status', 'resolved')
        ->exists())->toBeTrue();
});

it('admin dismisses a report and status is dismissed in DB', function () use ($makeAdmin, $makeReport) {
    $admin = $makeAdmin();
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
    $admin = $makeAdmin();
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
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    // One pending verification
    $pendingWorker = User::factory()->create(['role' => 'worker']);
    WorkerProfile::factory()->create([
        'user_id' => $pendingWorker->id,
        'verification_status' => 'pending',
    ]);

    // Bookings
    $activeBooking = Booking::factory()->create([
        'client_id' => $client->id,
        'worker_id' => $worker->id,
        'service_category_id' => $category->id,
        'status' => 'active',
    ]);
    $completedBooking = Booking::factory()->create([
        'client_id' => $client->id,
        'worker_id' => $worker->id,
        'service_category_id' => $category->id,
        'status' => 'completed',
    ]);

    // Report under review
    Report::create([
        'booking_id' => $completedBooking->id,
        'reported_by' => $client->id,
        'reported_user' => $worker->id,
        'reason' => 'misconduct',
        'description' => 'Test',
        'status' => 'under_review',
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
                'activity_trend' => [
                    ['date', 'label', 'bookings', 'verifications', 'active_jobs'],
                ],
            ],
        ]);

    $data = $response->json('data');

    expect($data['activity_trend'])->toHaveCount(7);
    expect($data['activity_trend'][0]['bookings'])->toBeInt();
    expect($data['activity_trend'][0]['verifications'])->toBeInt();
    expect($data['activity_trend'][0]['active_jobs'])->toBeInt();

    // Integer fields must be non-negative integers
    $integerFields = [
        'total_users', 'total_workers', 'total_clients',
        'pending_verifications', 'total_bookings',
        'active_bookings', 'completed_bookings',
        'total_reports', 'open_reports',
    ];

    foreach ($integerFields as $key) {
        expect($data[$key])->toBeInt("Expected {$key} to be an integer");
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
