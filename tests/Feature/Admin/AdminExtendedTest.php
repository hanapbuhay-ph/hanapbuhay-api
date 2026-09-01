<?php

use App\Models\AdminAuditLog;
use App\Models\Booking;
use App\Models\JobPost;
use App\Models\RatingReview;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'is_active' => true]);
}

function makeBookingWithStatus(string $status): Booking
{
    $client   = makeBookingClient();
    $worker   = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    return Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => $status,
    ]);
}

function makeRating(User $worker, User $client): RatingReview
{
    $category = ServiceCategory::factory()->create();
    $booking  = Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => 'completed',
    ]);

    return RatingReview::create([
        'booking_id' => $booking->id,
        'rated_by'   => $client->id,
        'rated_user' => $worker->id,
        'score'      => 4,
        'comment'    => 'Good job',
    ]);
}

// ══════════════════════════════════════════════════════════════════════════════
// TRUST TIER
// ══════════════════════════════════════════════════════════════════════════════

it('admin can update a worker trust tier', function () {
    $admin   = makeAdmin();
    $worker  = makeApprovedBookingWorker();
    $profile = $worker->workerProfile;

    $this->mock(NotificationService::class)
        ->shouldReceive('sendPush')->once();

    $this->actingAs($admin)
        ->postJson("/api/admin/workers/{$profile->id}/trust-tier", [
            'trust_tier' => 'trusted',
            'remarks'    => 'Consistently excellent service.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Worker trust tier updated.')
        ->assertJsonPath('data.trust_tier', 'trusted');

    expect($profile->fresh()->trust_tier)->toBe('trusted');
});

it('audit log is written when trust tier is updated', function () {
    $admin   = makeAdmin();
    $worker  = makeApprovedBookingWorker();
    $profile = $worker->workerProfile;

    $this->mock(NotificationService::class)->shouldReceive('sendPush')->once();

    $this->actingAs($admin)
        ->postJson("/api/admin/workers/{$profile->id}/trust-tier", [
            'trust_tier' => 'flagged',
            'remarks'    => 'Multiple complaints.',
        ]);

    expect(AdminAuditLog::where('admin_id', $admin->id)->where('action', 'update_trust_tier')->exists())->toBeTrue();
});

it('returns 422 when trust_tier value is invalid', function () {
    $admin   = makeAdmin();
    $profile = WorkerProfile::factory()->create();

    $this->actingAs($admin)
        ->postJson("/api/admin/workers/{$profile->id}/trust-tier", [
            'trust_tier' => 'superstar',
            'remarks'    => 'test',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['trust_tier']);
});

it('returns 404 for non-existent worker profile on trust tier update', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->postJson('/api/admin/workers/99999/trust-tier', [
            'trust_tier' => 'trusted',
            'remarks'    => 'test',
        ])
        ->assertStatus(404);
});

// ══════════════════════════════════════════════════════════════════════════════
// FORCE CANCEL BOOKING
// ══════════════════════════════════════════════════════════════════════════════

it('admin can force-cancel a pending booking', function () {
    $admin   = makeAdmin();
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
    $admin   = makeAdmin();
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
    $admin   = makeAdmin();
    $booking = makeBookingWithStatus('completed');

    $this->actingAs($admin)
        ->postJson("/api/admin/bookings/{$booking->id}/cancel", [
            'reason' => 'Too late.',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 422 when trying to force-cancel an already cancelled booking', function () {
    $admin   = makeAdmin();
    $booking = makeBookingWithStatus('cancelled');

    $this->actingAs($admin)
        ->postJson("/api/admin/bookings/{$booking->id}/cancel", [
            'reason' => 'Already done.',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 422 when reason is missing on force cancel', function () {
    $admin   = makeAdmin();
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
    $admin  = makeAdmin();
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
                'ratings'    => [['id', 'score', 'comment', 'rated_by', 'rated_user', 'booking']],
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ]);
});

it('admin can filter ratings by worker_id', function () {
    $admin   = makeAdmin();
    $worker1 = makeApprovedBookingWorker();
    $worker2 = makeApprovedBookingWorker();
    $client  = makeBookingClient();

    makeRating($worker1, $client);
    makeRating($worker2, $client);

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/ratings?worker_id={$worker1->id}")
        ->assertStatus(200);

    expect($response->json('data.pagination.total'))->toBe(1);
    expect($response->json('data.ratings.0.rated_user.id'))->toBe($worker1->id);
});

it('admin can delete a rating and worker average is recalculated', function () {
    $admin  = makeAdmin();
    $worker = makeApprovedBookingWorker();
    $client = makeBookingClient();

    $profile = $worker->workerProfile;
    $rating  = makeRating($worker, $client);

    // Manually set the worker's stats
    $profile->update(['average_rating' => 4.00, 'total_reviews' => 1]);

    $this->actingAs($admin)
        ->deleteJson("/api/admin/ratings/{$rating->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Rating deleted and worker average recalculated.');

    expect(RatingReview::find($rating->id))->toBeNull();
    expect($profile->fresh()->total_reviews)->toBe(0);
    expect((float) $profile->fresh()->average_rating)->toBe(0.0);
});

it('audit log is written when rating is deleted', function () {
    $admin  = makeAdmin();
    $worker = makeApprovedBookingWorker();
    $client = makeBookingClient();
    $rating = makeRating($worker, $client);

    $this->actingAs($admin)->deleteJson("/api/admin/ratings/{$rating->id}");

    expect(AdminAuditLog::where('admin_id', $admin->id)->where('action', 'delete_rating')->exists())->toBeTrue();
});

it('returns 404 when deleting a non-existent rating', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->deleteJson('/api/admin/ratings/99999')
        ->assertStatus(404);
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
                'logs'       => [['id', 'action', 'target_type', 'target_id', 'admin', 'created_at']],
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
            'name'      => 'Solar Panel Cleaning',
            'icon'      => 'solar',
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
    $admin    = makeAdmin();
    $category = ServiceCategory::factory()->create(['name' => 'Old Name', 'is_active' => true]);

    $this->actingAs($admin)
        ->patchJson("/api/admin/settings/categories/{$category->id}", [
            'name'      => 'Updated Name',
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
    $admin    = makeAdmin();

    $this->actingAs($admin)->postJson('/api/admin/settings/categories', ['name' => 'Tile Works']);

    expect(AdminAuditLog::where('admin_id', $admin->id)->where('action', 'create_category')->exists())->toBeTrue();

    $category = ServiceCategory::where('name', 'Tile Works')->first();

    $this->actingAs($admin)->patchJson("/api/admin/settings/categories/{$category->id}", ['name' => 'Tile Installation']);

    expect(AdminAuditLog::where('admin_id', $admin->id)->where('action', 'update_category')->exists())->toBeTrue();
});

// ══════════════════════════════════════════════════════════════════════════════
// JOB POST OVERSIGHT
// ══════════════════════════════════════════════════════════════════════════════

it('admin can list all job posts including soft-deleted', function () {
    $admin   = makeAdmin();
    $worker  = makeApprovedBookingWorker();
    $profile = $worker->workerProfile;
    $cat     = ServiceCategory::factory()->create();

    $post = JobPost::factory()->create([
        'worker_profile_id'   => $profile->id,
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
                'posts'      => [['id', 'title', 'is_active', 'category', 'worker']],
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ]);

    // Soft-deleted post should appear for admin
    $ids = collect($response->json('data.posts'))->pluck('id');
    expect($ids)->toContain($post->id);
});

it('admin can filter job posts by category_id', function () {
    $admin   = makeAdmin();
    $worker  = makeApprovedBookingWorker();
    $profile = $worker->workerProfile;
    $cat1    = ServiceCategory::factory()->create();
    $cat2    = ServiceCategory::factory()->create();

    JobPost::factory()->create(['worker_profile_id' => $profile->id, 'service_category_id' => $cat1->id]);
    JobPost::factory()->create(['worker_profile_id' => $profile->id, 'service_category_id' => $cat2->id]);

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/posts?category_id={$cat1->id}")
        ->assertStatus(200);

    expect($response->json('data.pagination.total'))->toBe(1);
});

it('admin can hard-delete a job post', function () {
    $admin   = makeAdmin();
    $worker  = makeApprovedBookingWorker();
    $profile = $worker->workerProfile;
    $cat     = ServiceCategory::factory()->create();

    $post = JobPost::factory()->create([
        'worker_profile_id'   => $profile->id,
        'service_category_id' => $cat->id,
    ]);

    $this->actingAs($admin)
        ->deleteJson("/api/admin/posts/{$post->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Job post permanently deleted.');

    // Hard deleted — must not exist even with withTrashed
    expect(JobPost::withTrashed()->find($post->id))->toBeNull();
});

it('audit log is written when job post is hard-deleted', function () {
    $admin   = makeAdmin();
    $worker  = makeApprovedBookingWorker();
    $profile = $worker->workerProfile;
    $cat     = ServiceCategory::factory()->create();

    $post = JobPost::factory()->create([
        'worker_profile_id'   => $profile->id,
        'service_category_id' => $cat->id,
    ]);

    $this->actingAs($admin)->deleteJson("/api/admin/posts/{$post->id}");

    expect(AdminAuditLog::where('admin_id', $admin->id)->where('action', 'delete_job_post')->exists())->toBeTrue();
});

it('returns 404 when hard-deleting a non-existent post', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->deleteJson('/api/admin/posts/99999')
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
