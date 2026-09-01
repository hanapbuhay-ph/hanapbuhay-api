<?php

use App\Models\AdminAuditLog;
use App\Models\Barangay;
use App\Models\Booking;
use App\Models\RatingReview;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeFilterAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'is_active' => true]);
}

function makeRatingBetween(User $rater, User $rated, int $score = 4): RatingReview
{
    $category = ServiceCategory::factory()->create();
    $booking  = Booking::factory()->create([
        'client_id'           => $rater->id,
        'worker_id'           => $rated->id,
        'service_category_id' => $category->id,
        'status'              => 'completed',
    ]);

    return RatingReview::create([
        'booking_id' => $booking->id,
        'rated_by'   => $rater->id,
        'rated_user' => $rated->id,
        'score'      => $score,
        'comment'    => 'Good work',
    ]);
}

// ══════════════════════════════════════════════════════════════════════════════
// GAP 7a — GET /admin/users — new filter params
// ══════════════════════════════════════════════════════════════════════════════

it('admin users: filters by barangay param', function () {
    $admin = makeFilterAdmin();
    $b1    = Barangay::factory()->create();
    $b2    = Barangay::factory()->create();

    $u1 = User::factory()->create(['role' => 'client', 'barangay_id' => $b1->id]);
    $u2 = User::factory()->create(['role' => 'client', 'barangay_id' => $b2->id]);

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/users?barangay={$b1->id}")
        ->assertStatus(200);

    $ids = collect($response->json('data.users'))->pluck('id');
    expect($ids)->toContain($u1->id);
    expect($ids)->not->toContain($u2->id);
});

it('admin users: filters by status=active', function () {
    $admin   = makeFilterAdmin();
    $active  = User::factory()->create(['role' => 'client', 'is_active' => true]);
    $suspended = User::factory()->create(['role' => 'client', 'is_active' => false]);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/users?status=active')
        ->assertStatus(200);

    $ids = collect($response->json('data.users'))->pluck('id');
    expect($ids)->toContain($active->id);
    expect($ids)->not->toContain($suspended->id);
});

it('admin users: filters by status=suspended', function () {
    $admin     = makeFilterAdmin();
    $active    = User::factory()->create(['role' => 'client', 'is_active' => true]);
    $suspended = User::factory()->create(['role' => 'client', 'is_active' => false]);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/users?status=suspended')
        ->assertStatus(200);

    $ids = collect($response->json('data.users'))->pluck('id');
    expect($ids)->toContain($suspended->id);
    expect($ids)->not->toContain($active->id);
});

// ══════════════════════════════════════════════════════════════════════════════
// GAP 7b — GET /admin/bookings — new filter params
// ══════════════════════════════════════════════════════════════════════════════

it('admin bookings: filters by category_id', function () {
    $admin = makeFilterAdmin();
    $cat1  = ServiceCategory::factory()->create();
    $cat2  = ServiceCategory::factory()->create();

    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();

    $b1 = Booking::factory()->create(['client_id' => $client->id, 'worker_id' => $worker->id, 'service_category_id' => $cat1->id]);
    $b2 = Booking::factory()->create(['client_id' => $client->id, 'worker_id' => $worker->id, 'service_category_id' => $cat2->id]);

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/bookings?category_id={$cat1->id}")
        ->assertStatus(200);

    $ids = collect($response->json('data.bookings'))->pluck('id');
    expect($ids)->toContain($b1->id);
    expect($ids)->not->toContain($b2->id);
});

it('admin bookings: filters by date_from and date_to', function () {
    $admin  = makeFilterAdmin();
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();
    $cat    = ServiceCategory::factory()->create();

    $oldBooking = Booking::factory()->create([
        'client_id' => $client->id, 'worker_id' => $worker->id,
        'service_category_id' => $cat->id, 'created_at' => now()->subMonths(3),
    ]);
    $newBooking = Booking::factory()->create([
        'client_id' => $client->id, 'worker_id' => $worker->id,
        'service_category_id' => $cat->id, 'created_at' => now(),
    ]);

    $from = now()->subWeek()->toDateString();
    $to   = now()->addDay()->toDateString();

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/bookings?date_from={$from}&date_to={$to}")
        ->assertStatus(200);

    $ids = collect($response->json('data.bookings'))->pluck('id');
    expect($ids)->toContain($newBooking->id);
    expect($ids)->not->toContain($oldBooking->id);
});

it('admin bookings: filters by search (booking code)', function () {
    $admin  = makeFilterAdmin();
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();
    $cat    = ServiceCategory::factory()->create();

    $booking = Booking::factory()->create([
        'client_id' => $client->id, 'worker_id' => $worker->id,
        'service_category_id' => $cat->id,
    ]);

    $code = $booking->booking_code;

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/bookings?search={$code}")
        ->assertStatus(200);

    $ids = collect($response->json('data.bookings'))->pluck('id');
    expect($ids)->toContain($booking->id);
});

it('admin bookings: filters by search (client name)', function () {
    $admin  = makeFilterAdmin();
    $client = User::factory()->create(['role' => 'client', 'name' => 'Unique Client Name XYZ']);
    $worker = makeApprovedBookingWorker();
    $cat    = ServiceCategory::factory()->create();

    $booking = Booking::factory()->create([
        'client_id' => $client->id, 'worker_id' => $worker->id,
        'service_category_id' => $cat->id,
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/bookings?search=Unique+Client+Name+XYZ')
        ->assertStatus(200);

    $ids = collect($response->json('data.bookings'))->pluck('id');
    expect($ids)->toContain($booking->id);
});

// ══════════════════════════════════════════════════════════════════════════════
// GAP 8 — GET /admin/ratings — new filter params
// ══════════════════════════════════════════════════════════════════════════════

it('admin ratings: filters by score', function () {
    $admin  = makeFilterAdmin();
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();

    $r5 = makeRatingBetween($client, $worker, 5);
    $r3 = makeRatingBetween($client, $worker->fresh(), 3); // need separate booking

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/ratings?score=5')
        ->assertStatus(200);

    $ids = collect($response->json('data.ratings'))->pluck('id');
    expect($ids)->toContain($r5->id);
    expect($ids)->not->toContain($r3->id);
});

it('admin ratings: filters by direction=client_to_worker', function () {
    $admin  = makeFilterAdmin();
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();

    $clientToWorker = makeRatingBetween($client, $worker, 4);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/ratings?direction=client_to_worker')
        ->assertStatus(200);

    $ids = collect($response->json('data.ratings'))->pluck('id');
    expect($ids)->toContain($clientToWorker->id);
});

it('admin ratings: filters by search (name)', function () {
    $admin  = makeFilterAdmin();
    $client = User::factory()->create(['role' => 'client', 'name' => 'SearchableClientABC']);
    $worker = makeApprovedBookingWorker();

    $rating = makeRatingBetween($client, $worker, 5);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/ratings?search=SearchableClientABC')
        ->assertStatus(200);

    $ids = collect($response->json('data.ratings'))->pluck('id');
    expect($ids)->toContain($rating->id);
});

// ══════════════════════════════════════════════════════════════════════════════
// GAP 9 — GET /admin/audit-logs — new filter params
// ══════════════════════════════════════════════════════════════════════════════

it('admin audit logs: filters by target_type', function () {
    $admin = makeFilterAdmin();

    AdminAuditLog::factory()->create(['admin_id' => $admin->id, 'action' => 'test', 'target_type' => 'WorkerProfile', 'target_id' => 1]);
    AdminAuditLog::factory()->create(['admin_id' => $admin->id, 'action' => 'test', 'target_type' => 'Booking', 'target_id' => 1]);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/audit-logs?target_type=WorkerProfile')
        ->assertStatus(200);

    $types = collect($response->json('data.logs'))->pluck('target_type');
    expect($types->every(fn ($t) => $t === 'WorkerProfile'))->toBeTrue();
});

it('admin audit logs: filters by date_from and date_to', function () {
    $admin = makeFilterAdmin();

    $oldLog = AdminAuditLog::factory()->create([
        'admin_id'    => $admin->id,
        'action'      => 'old_action',
        'target_type' => 'User',
        'target_id'   => 1,
        'created_at'  => now()->subMonths(2),
    ]);

    $newLog = AdminAuditLog::factory()->create([
        'admin_id'    => $admin->id,
        'action'      => 'new_action',
        'target_type' => 'User',
        'target_id'   => 2,
        'created_at'  => now(),
    ]);

    $from = now()->subWeek()->toDateString();
    $to   = now()->addDay()->toDateString();

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/audit-logs?date_from={$from}&date_to={$to}")
        ->assertStatus(200);

    $ids = collect($response->json('data.logs'))->pluck('id');
    expect($ids)->toContain($newLog->id);
    expect($ids)->not->toContain($oldLog->id);
});

it('admin audit logs: existing admin_id and action filters still work', function () {
    $admin1 = makeFilterAdmin();
    $admin2 = makeFilterAdmin();

    AdminAuditLog::factory()->create(['admin_id' => $admin1->id, 'action' => 'approve_verification', 'target_type' => 'WorkerProfile', 'target_id' => 1]);
    AdminAuditLog::factory()->create(['admin_id' => $admin2->id, 'action' => 'delete_rating', 'target_type' => 'RatingReview', 'target_id' => 1]);

    $response = $this->actingAs($admin1)
        ->getJson("/api/admin/audit-logs?admin_id={$admin1->id}&action=approve_verification")
        ->assertStatus(200);

    expect($response->json('data.pagination.total'))->toBe(1);
    expect($response->json('data.logs.0.action'))->toBe('approve_verification');
});
