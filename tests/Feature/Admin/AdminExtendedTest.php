<?php

use App\Models\AdminAuditLog;
use App\Models\Booking;
use App\Models\HanapbuhayNotification;
use App\Models\JobPost;
use App\Models\RatingReview;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Services\Notification\NotificationService;
use App\Services\SecurityEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'is_active' => true]);
}

function makeBookingWithStatus(string $status): Booking
{
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    return Booking::factory()->create([
        'client_id' => $client->id,
        'worker_id' => $worker->id,
        'service_category_id' => $category->id,
        'status' => $status,
    ]);
}

function makeRating(User $worker, User $client): RatingReview
{
    $category = ServiceCategory::factory()->create();
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'worker_id' => $worker->id,
        'service_category_id' => $category->id,
        'status' => 'completed',
    ]);

    return RatingReview::create([
        'booking_id' => $booking->id,
        'rated_by' => $client->id,
        'rated_user' => $worker->id,
        'score' => 4,
        'comment' => 'Good job',
    ]);
}

// ══════════════════════════════════════════════════════════════════════════════
// TRUST TIER
// ══════════════════════════════════════════════════════════════════════════════

it('admin can update a worker trust tier', function () {
    $admin = makeAdmin();
    $worker = makeApprovedBookingWorker();
    $profile = $worker->workerProfile;

    $notificationService = $this->mock(NotificationService::class);
    $notificationService->shouldReceive('notify')->once()
        ->andReturnUsing(fn () => new HanapbuhayNotification);

    $this->actingAs($admin)
        ->postJson("/api/admin/workers/{$profile->id}/trust-tier", [
            'trust_tier' => 'trusted',
            'remarks' => 'Consistently excellent service.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Worker trust tier updated.')
        ->assertJsonPath('data.trust_tier', 'trusted');

    expect($profile->fresh()->trust_tier)->toBe('trusted');
});

it('warns the worker when their revoked trust tier hides job posts from the client feed', function () {
    $admin = makeAdmin();
    $worker = makeApprovedBookingWorker();
    $profile = $worker->workerProfile;

    $notificationService = $this->mock(NotificationService::class);
    $notificationService->shouldReceive('notify')
        ->once()
        ->withArgs(function (User $user, string $title, string $message, string $type, array $data) use ($worker): bool {
            return $user->id === $worker->id
                && $title === 'Trust Tier Updated'
                && $message === 'Your trust tier has been revoked. Your job posts will no longer appear in the client home feed.'
                && $type === 'trust_tier_updated'
                && ($data['posts_hidden_from_client_feed'] ?? false) === true;
        })
        ->andReturnUsing(fn () => new HanapbuhayNotification);

    $this->actingAs($admin)
        ->postJson("/api/admin/workers/{$profile->id}/trust-tier", [
            'trust_tier' => 'revoked',
            'remarks' => 'Trust revoked after review.',
        ])
        ->assertOk()
        ->assertJsonPath('data.trust_tier', 'revoked');

    expect($profile->fresh()->trust_tier)->toBe('revoked');
});

it('audit log is written when trust tier is updated', function () {
    $admin = makeAdmin();
    $worker = makeApprovedBookingWorker();
    $profile = $worker->workerProfile;

    $notificationService = $this->mock(NotificationService::class);
    $notificationService->shouldReceive('notify')->once()
        ->andReturnUsing(fn () => new HanapbuhayNotification);

    $this->actingAs($admin)
        ->postJson("/api/admin/workers/{$profile->id}/trust-tier", [
            'trust_tier' => 'flagged',
            'remarks' => 'Multiple complaints.',
        ]);

    expect(AdminAuditLog::where('admin_id', $admin->id)->where('action', 'update_trust_tier')->exists())->toBeTrue();
});

it('returns 422 when trust_tier value is invalid', function () {
    $admin = makeAdmin();
    $profile = WorkerProfile::factory()->create();

    $this->actingAs($admin)
        ->postJson("/api/admin/workers/{$profile->id}/trust-tier", [
            'trust_tier' => 'superstar',
            'remarks' => 'test',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['trust_tier']);
});

it('returns 404 for non-existent worker profile on trust tier update', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->postJson('/api/admin/workers/99999/trust-tier', [
            'trust_tier' => 'trusted',
            'remarks' => 'test',
        ])
        ->assertStatus(404);
});

// ══════════════════════════════════════════════════════════════════════════════
// FORCE CANCEL BOOKING
// ══════════════════════════════════════════════════════════════════════════════

it('admin can force-cancel a pending booking', function () {
    $admin = makeAdmin();
    $booking = makeBookingWithStatus('pending');

    $this->mock(NotificationService::class)->shouldReceive('sendPush')->twice();

    $this->actingAs($admin)
        ->postJson("/api/admin/bookings/{$booking->id}/cancel", [
            'reason' => 'Policy violation detected.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Booking force-cancelled.')
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancelled_by', 'admin');

    expect($booking->fresh()->status)->toBe('cancelled');
});

it('admin can force-cancel an active booking', function () {
    $admin = makeAdmin();
    $booking = makeBookingWithStatus('active');

    $this->mock(NotificationService::class)->shouldReceive('sendPush')->twice();

    $this->actingAs($admin)
        ->postJson("/api/admin/bookings/{$booking->id}/cancel", [
            'reason' => 'Emergency cancellation.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'cancelled');
});

it('returns 422 when trying to force-cancel an already completed booking', function () {
    $admin = makeAdmin();
    $booking = makeBookingWithStatus('completed');

    $this->actingAs($admin)
        ->postJson("/api/admin/bookings/{$booking->id}/cancel", [
            'reason' => 'Too late.',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 422 when trying to force-cancel an already cancelled booking', function () {
    $admin = makeAdmin();
    $booking = makeBookingWithStatus('cancelled');

    $this->actingAs($admin)
        ->postJson("/api/admin/bookings/{$booking->id}/cancel", [
            'reason' => 'Already done.',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 422 when reason is missing on force cancel', function () {
    $admin = makeAdmin();
    $booking = makeBookingWithStatus('pending');

    $this->actingAs($admin)
        ->postJson("/api/admin/bookings/{$booking->id}/cancel", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);
});

// ══════════════════════════════════════════════════════════════════════════════
// RATINGS OVERSIGHT
// ══════════════════════════════════════════════════════════════════════════════

it('admin can list all ratings with pagination', function () {
    $admin = makeAdmin();
    $worker = makeApprovedBookingWorker();
    $client = makeBookingClient();

    makeRating($worker, $client);

    $this->actingAs($admin)
        ->getJson('/api/admin/ratings')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Ratings retrieved.')
        ->assertJsonStructure([
            'data' => [
                'ratings' => [['id', 'score', 'comment', 'rated_by', 'rated_user', 'booking']],
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ]);
});

it('admin can filter ratings by worker_id', function () {
    $admin = makeAdmin();
    $worker1 = makeApprovedBookingWorker();
    $worker2 = makeApprovedBookingWorker();
    $client = makeBookingClient();

    makeRating($worker1, $client);
    makeRating($worker2, $client);

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/ratings?worker_id={$worker1->id}")
        ->assertStatus(200);

    expect($response->json('data.pagination.total'))->toBe(1);
    expect($response->json('data.ratings.0.rated_user.id'))->toBe($worker1->id);
});

it('admin can list review recipients with review counts and average ratings', function () {
    $admin = makeAdmin();
    $worker = makeApprovedBookingWorker();
    $client = makeBookingClient();
    $clientWithoutReviews = makeBookingClient();
    $workerRating = makeRating($worker, $client);

    RatingReview::create([
        'booking_id' => $workerRating->booking_id,
        'rated_by' => $worker->id,
        'rated_user' => $client->id,
        'score' => 5,
        'comment' => 'Great client',
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/ratings/users')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'users' => [[
                    'id', 'name', 'email', 'role', 'barangay', 'is_active',
                    'reviews_received_count', 'average_rating',
                ]],
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ]);

    $recipients = collect($response->json('data.users'))->keyBy('id');
    expect($recipients[$worker->id]['role'])->toBe('worker')
        ->and($recipients[$worker->id]['reviews_received_count'])->toBe(1)
        ->and($recipients[$worker->id]['average_rating'])->toBe(4)
        ->and($recipients[$client->id]['role'])->toBe('client')
        ->and($recipients[$client->id]['reviews_received_count'])->toBe(1)
        ->and($recipients[$client->id]['average_rating'])->toBe(5)
        ->and($recipients[$clientWithoutReviews->id]['reviews_received_count'])->toBe(0)
        ->and($recipients[$clientWithoutReviews->id]['average_rating'])->toBe(0)
        ->and($recipients->has($admin->id))->toBeFalse();

    $this->actingAs($admin)
        ->getJson('/api/admin/ratings/users?role=worker&search='.urlencode($worker->name))
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.users.0.id', $worker->id);

    $this->actingAs($admin)
        ->getJson('/api/admin/ratings/users?sort_by=name&sort_direction=asc')
        ->assertOk()
        ->assertJsonPath('data.users.0.name', collect($recipients)->pluck('name')->sort()->first());

    $this->actingAs($admin)
        ->getJson('/api/admin/ratings/users?sort_by=average_rating&sort_direction=asc')
        ->assertOk()
        ->assertJsonPath('data.users.0.id', $clientWithoutReviews->id);

    $this->actingAs($admin)
        ->getJson('/api/admin/ratings/users?sort_by=average_rating&sort_direction=desc')
        ->assertOk()
        ->assertJsonPath('data.users.0.id', $client->id);

    $this->actingAs($admin)
        ->getJson('/api/admin/ratings/users?sort_by=email')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort_by']);
});

it('admin can filter ratings by rated_user_id for clients and workers', function () {
    $admin = makeAdmin();
    $worker = makeApprovedBookingWorker();
    $client = makeBookingClient();
    $workerRating = makeRating($worker, $client);

    RatingReview::create([
        'booking_id' => $workerRating->booking_id,
        'rated_by' => $worker->id,
        'rated_user' => $client->id,
        'score' => 5,
        'comment' => 'Great client',
    ]);

    $this->actingAs($admin)
        ->getJson("/api/admin/ratings?rated_user_id={$client->id}")
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.ratings.0.rated_user.id', $client->id);

    $this->actingAs($admin)
        ->getJson("/api/admin/ratings?rated_user_id={$worker->id}")
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.ratings.0.rated_user.id', $worker->id);
});

it('neither admins nor super admins can remove a rating', function () {
    $admin = makeAdmin();
    $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    $worker = makeApprovedBookingWorker();
    $client = makeBookingClient();

    $profile = $worker->workerProfile;
    $rating = makeRating($worker, $client);

    $profile->update(['average_rating' => 4.00, 'total_reviews' => 1]);

    $this->actingAs($admin)
        ->deleteJson("/api/admin/ratings/{$rating->id}")
        ->assertForbidden()
        ->assertJsonPath('message', 'Review removal is disabled.');

    $this->actingAs($superAdmin)
        ->deleteJson("/api/admin/ratings/{$rating->id}")
        ->assertForbidden()
        ->assertJsonPath('message', 'Review removal is disabled.');

    expect(RatingReview::find($rating->id))->not->toBeNull()
        ->and((int) $profile->fresh()->total_reviews)->toBe(1)
        ->and((float) $profile->fresh()->average_rating)->toBe(4.0)
        ->and(AdminAuditLog::where('action', 'delete_rating')->exists())->toBeFalse();
});

it('rejects rating removal even when the requested rating does not exist', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->deleteJson('/api/admin/ratings/99999')
        ->assertForbidden()
        ->assertJsonPath('message', 'Review removal is disabled.');
});

// ══════════════════════════════════════════════════════════════════════════════
// AUDIT LOGS
// ══════════════════════════════════════════════════════════════════════════════

it('admin can list audit logs with pagination', function () {
    $admin = makeAdmin();

    AdminAuditLog::factory()->count(3)->create(['admin_id' => $admin->id]);

    $this->actingAs($admin)
        ->getJson('/api/admin/audit-logs')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Audit logs retrieved.')
        ->assertJsonStructure([
            'data' => [
                'logs' => [['id', 'category', 'outcome', 'action', 'target_type', 'target_id', 'admin', 'created_at']],
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ]);
});

it('admin can filter audit logs by admin_id', function () {
    $admin1 = makeAdmin();
    $admin2 = makeAdmin();

    AdminAuditLog::factory()->count(2)->create(['admin_id' => $admin1->id]);
    AdminAuditLog::factory()->count(3)->create(['admin_id' => $admin2->id]);

    $response = $this->actingAs($admin1)
        ->getJson("/api/admin/audit-logs?admin_id={$admin1->id}")
        ->assertStatus(200);

    expect($response->json('data.pagination.total'))->toBe(2);
});

it('admin can filter security logs by category outcome IP and actor identity', function () {
    $admin = makeAdmin();
    $logger = app(SecurityEventLogger::class);
    $logger->record('login', 'failure', null, null, ['email' => 'unknown@example.com'], '192.0.2.12');
    $logger->record('login', 'success', $admin, $admin->id, [], '192.0.2.13');

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/audit-logs?category=security&outcome=failure&ip_address=192.0.2.12&admin_name=unknown%40example.com')
        ->assertOk()
        ->assertJsonPath('data.logs.0.action', 'login')
        ->assertJsonPath('data.logs.0.admin.name', 'unknown@example.com')
        ->assertJsonPath('data.logs.0.admin.email', 'unknown@example.com');

    expect($response->json('data.pagination.total'))->toBe(1);
});

it('exports all filtered audit and security logs as a safe CSV', function () {
    $admin = makeAdmin();
    AdminAuditLog::factory()->create([
        'admin_id' => $admin->id,
        'actor_name' => '=Audit Tester',
        'actor_email' => 'audit@example.com',
        'category' => 'security',
        'outcome' => 'failure',
        'action' => 'login_failure',
        'target_type' => 'User',
        'target_id' => 42,
        'details' => ['reason' => 'invalid credentials'],
        'ip_address' => '203.0.113.40',
        'user_agent' => 'HanapBuhay Test Client',
        'created_at' => now(),
    ]);
    AdminAuditLog::factory()->create([
        'admin_id' => $admin->id,
        'actor_name' => 'Different Actor',
        'category' => 'admin_activity',
        'outcome' => 'success',
        'action' => 'update_user',
        'target_type' => 'User',
        'ip_address' => '203.0.113.41',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin)
        ->get('/api/admin/audit-logs/export?category=security&outcome=failure&action=login&target_type=User&admin_name=Audit%20Tester&ip_address=203.0.113.40&date_from='.today()->toDateString().'&date_to='.today()->toDateString())
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();
    $rows = array_map('str_getcsv', explode("\n", trim(substr($csv, 3))));

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($rows[0])->toBe([
            'Log ID',
            'Category',
            'Outcome',
            'Action',
            'Actor',
            'Actor Email',
            'Target Type',
            'Target ID',
            'Details',
            'IP Address',
            'User Agent',
            'Created At',
        ])
        ->and($rows)->toHaveCount(2)
        ->and($rows[1][4])->toBe("'=Audit Tester")
        ->and($rows[1][8])->toBe('{"reason":"invalid credentials"}')
        ->and($csv)->not->toContain('Different Actor');

    $this->actingAs(makeBookingClient())
        ->getJson('/api/admin/audit-logs/export')
        ->assertForbidden();
});

it('regular admins cannot delete audit or security logs', function () {
    $admin = makeAdmin();

    AdminAuditLog::factory()->count(3)->create(['admin_id' => $admin->id]);

    $this->actingAs($admin)
        ->deleteJson('/api/admin/audit-logs')
        ->assertForbidden()
        ->assertJsonPath('message', 'Only the super admin can delete audit and security logs.');

    $log = AdminAuditLog::first();
    $this->actingAs($admin)
        ->deleteJson("/api/admin/audit-logs/{$log->id}")
        ->assertForbidden();

    expect(AdminAuditLog::count())->toBe(3);
});

it('super admin can delete an individual audit log and all remaining logs', function () {
    $superAdmin = User::factory()->create(['role' => 'super_admin']);
    $logs = AdminAuditLog::factory()->count(3)->create(['admin_id' => $superAdmin->id]);

    $this->actingAs($superAdmin)
        ->deleteJson("/api/admin/audit-logs/{$logs[0]->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Audit log deleted.');

    expect(AdminAuditLog::count())->toBe(2);

    $this->actingAs($superAdmin)
        ->deleteJson('/api/admin/audit-logs')
        ->assertOk()
        ->assertJsonPath('message', 'All audit and security logs deleted.')
        ->assertJsonPath('data.deleted_count', 2);

    expect(AdminAuditLog::count())->toBe(0);
});

it('returns empty logs when none exist', function () {
    $admin = makeAdmin();

    $response = $this->actingAs($admin)->getJson('/api/admin/audit-logs')->assertStatus(200);

    expect($response->json('data.logs'))->toBeEmpty();
});

// ══════════════════════════════════════════════════════════════════════════════
// PLATFORM SETTINGS — SERVICE CATEGORIES
// ══════════════════════════════════════════════════════════════════════════════

it('admin can list all categories including inactive', function () {
    $admin = makeAdmin();

    ServiceCategory::factory()->create(['name' => 'Plumbing',    'is_active' => true]);
    ServiceCategory::factory()->create(['name' => 'Old Service', 'is_active' => false]);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/settings/categories')
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('Plumbing');
    expect($names)->toContain('Old Service');
});

it('admin can create a new service category', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->postJson('/api/admin/settings/categories', [
            'name' => 'Solar Panel Cleaning',
            'icon' => 'solar',
            'is_active' => true,
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Category created.')
        ->assertJsonPath('data.name', 'Solar Panel Cleaning');

    $this->assertDatabaseHas('service_categories', ['name' => 'Solar Panel Cleaning']);
});

it('returns 422 when creating a category with a duplicate name', function () {
    $admin = makeAdmin();
    ServiceCategory::factory()->create(['name' => 'Carpentry']);

    $this->actingAs($admin)
        ->postJson('/api/admin/settings/categories', ['name' => 'Carpentry'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('admin can update an existing category', function () {
    $admin = makeAdmin();
    $category = ServiceCategory::factory()->create(['name' => 'Old Name', 'is_active' => true]);

    $this->actingAs($admin)
        ->patchJson("/api/admin/settings/categories/{$category->id}", [
            'name' => 'Updated Name',
            'is_active' => false,
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Category updated.')
        ->assertJsonPath('data.name', 'Updated Name');

    expect($category->fresh()->name)->toBe('Updated Name');
    expect($category->fresh()->is_active)->toBeFalse();
});

it('audit log written on category create and update', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)->postJson('/api/admin/settings/categories', ['name' => 'Tile Works']);

    expect(AdminAuditLog::where('admin_id', $admin->id)->where('action', 'create_category')->exists())->toBeTrue();

    $category = ServiceCategory::where('name', 'Tile Works')->first();

    $this->actingAs($admin)->patchJson("/api/admin/settings/categories/{$category->id}", ['name' => 'Tile Installation']);

    expect(AdminAuditLog::where('admin_id', $admin->id)->where('action', 'update_category')->exists())->toBeTrue();
});

it('admin can create, list, update, and delete report reasons', function () {
    $admin = makeAdmin();

    $create = $this->actingAs($admin)
        ->postJson('/api/admin/settings/report-reasons', [
            'label' => 'Spam Listing',
            'description' => 'This listing appears to be spam.',
            'is_active' => true,
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.label', 'Spam Listing');

    $reasonId = $create->json('data.id');

    $this->actingAs($admin)
        ->getJson('/api/admin/settings/report-reasons')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonFragment(['label' => 'Spam Listing']);

    $this->actingAs($admin)
        ->patchJson("/api/admin/settings/report-reasons/{$reasonId}", [
            'label' => 'Spam Listing Updated',
            'description' => 'Updated description.',
            'is_active' => false,
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.label', 'Spam Listing Updated');

    $this->actingAs($admin)
        ->deleteJson("/api/admin/settings/report-reasons/{$reasonId}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('report_reasons', ['id' => $reasonId]);
});

// ══════════════════════════════════════════════════════════════════════════════
// JOB POST OVERSIGHT
// ══════════════════════════════════════════════════════════════════════════════

it('admin can list all job posts including soft-deleted', function () {
    $admin = makeAdmin();
    $worker = makeApprovedBookingWorker();
    $profile = $worker->workerProfile;
    $cat = ServiceCategory::factory()->create();

    $post = JobPost::factory()->create([
        'worker_profile_id' => $profile->id,
        'service_category_id' => $cat->id,
    ]);
    $post->delete(); // soft delete

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/posts')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Job posts retrieved.')
        ->assertJsonStructure([
            'data' => [
                'posts' => [['id', 'title', 'is_active', 'category', 'worker']],
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ]);

    // Soft-deleted post should appear for admin
    $ids = collect($response->json('data.posts'))->pluck('id');
    expect($ids)->toContain($post->id);
});

it('admin can filter job posts by category_id', function () {
    $admin = makeAdmin();
    $worker = makeApprovedBookingWorker();
    $profile = $worker->workerProfile;
    $cat1 = ServiceCategory::factory()->create();
    $cat2 = ServiceCategory::factory()->create();

    JobPost::factory()->create(['worker_profile_id' => $profile->id, 'service_category_id' => $cat1->id]);
    JobPost::factory()->create(['worker_profile_id' => $profile->id, 'service_category_id' => $cat2->id]);

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/posts?category_id={$cat1->id}")
        ->assertStatus(200);

    expect($response->json('data.pagination.total'))->toBe(1);
});

it('admin can filter job posts by search, status, and worker verification', function () {
    $admin = makeAdmin();
    $worker = makeApprovedBookingWorker();
    $profile = $worker->workerProfile;
    $category = ServiceCategory::factory()->create();

    JobPost::factory()->create([
        'worker_profile_id' => $profile->id,
        'service_category_id' => $category->id,
        'title' => 'Emergency Plumbing Repair',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/posts?search=Emergency&status=active&verification=verified')
        ->assertStatus(200);

    expect($response->json('data.pagination.total'))->toBe(1);
    expect($response->json('data.posts.0.worker.verification_status'))->toBe('approved');
    expect($response->json('data.posts.0.worker.barangay'))->not->toBeNull();
});

it('admin can retrieve a job post detail with worker, images, and booking count', function () {
    $admin = makeAdmin();
    $worker = makeApprovedBookingWorker();
    $profile = $worker->workerProfile;
    $category = ServiceCategory::factory()->create();
    $post = JobPost::factory()->create([
        'worker_profile_id' => $profile->id,
        'service_category_id' => $category->id,
    ]);

    $this->actingAs($admin)
        ->getJson("/api/admin/posts/{$post->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.job_post.id', $post->id)
        ->assertJsonPath('data.job_post.worker.name', $worker->name)
        ->assertJsonPath('data.job_post.bookings_count', 0)
        ->assertJsonStructure(['data' => ['job_post' => ['description', 'category', 'worker', 'images']]]);
});

it('admin can hard-delete a job post', function () {
    $admin = makeAdmin();
    $worker = makeApprovedBookingWorker();
    $profile = $worker->workerProfile;
    $cat = ServiceCategory::factory()->create();

    $post = JobPost::factory()->create([
        'worker_profile_id' => $profile->id,
        'service_category_id' => $cat->id,
    ]);

    $this->actingAs($admin)
        ->deleteJson("/api/admin/posts/{$post->id}", ['reason' => 'Violates platform policies.'])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Job post permanently deleted.');

    // Hard deleted — must not exist even with withTrashed
    expect(JobPost::withTrashed()->find($post->id))->toBeNull();
});

it('audit log is written when job post is hard-deleted', function () {
    $admin = makeAdmin();
    $worker = makeApprovedBookingWorker();
    $profile = $worker->workerProfile;
    $cat = ServiceCategory::factory()->create();

    $post = JobPost::factory()->create([
        'worker_profile_id' => $profile->id,
        'service_category_id' => $cat->id,
    ]);

    $this->actingAs($admin)->deleteJson("/api/admin/posts/{$post->id}", [
        'reason' => 'Violates platform policies.',
    ]);

    expect(AdminAuditLog::where('admin_id', $admin->id)->where('action', 'delete_job_post')->exists())->toBeTrue();
});

it('returns 404 when hard-deleting a non-existent post', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->deleteJson('/api/admin/posts/99999', ['reason' => 'Invalid post cleanup.'])
        ->assertStatus(404);
});

// ── Access guard ──────────────────────────────────────────────────────────────

it('returns 403 for non-admin on all new admin routes', function () {
    $client = makeBookingClient();

    $this->actingAs($client)->postJson('/api/admin/workers/1/trust-tier', [])->assertStatus(403);
    $this->actingAs($client)->getJson('/api/admin/ratings')->assertStatus(403);
    $this->actingAs($client)->getJson('/api/admin/audit-logs')->assertStatus(403);
    $this->actingAs($client)->getJson('/api/admin/settings/categories')->assertStatus(403);
    $this->actingAs($client)->getJson('/api/admin/posts')->assertStatus(403);
});
