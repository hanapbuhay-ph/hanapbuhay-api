<?php

use App\Models\Booking;
use App\Models\JobPost;
use App\Models\RatingReview;
use App\Models\Report;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns period analytics and aggregate breakdowns to an admin', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create(['name' => 'Electrical']);
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'worker_id' => $worker->id,
        'service_category_id' => $category->id,
        'status' => 'completed',
    ]);
    $post = JobPost::factory()->create([
        'worker_profile_id' => $worker->workerProfile->id,
        'service_category_id' => $category->id,
    ]);
    Report::create([
        'booking_id' => $booking->id,
        'reported_by' => $client->id,
        'reported_user' => $worker->id,
        'reason' => 'no_show',
        'description' => 'The worker did not arrive.',
        'status' => 'under_review',
    ]);
    RatingReview::create([
        'booking_id' => $booking->id,
        'rated_by' => $client->id,
        'rated_user' => $worker->id,
        'score' => 4,
        'comment' => 'Good service.',
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/report-analytics?date_from='.today()->toDateString().'&date_to='.today()->toDateString())
        ->assertOk()
        ->assertJsonPath('data.period.interval', 'day')
        ->assertJsonPath('data.summary.bookings', 1)
        ->assertJsonPath('data.summary.job_posts', 1)
        ->assertJsonPath('data.summary.reports', 1)
        ->assertJsonPath('data.summary.reviews', 1)
        ->assertJsonPath('data.summary.average_review_score', 4)
        ->assertJsonPath('data.bookings_by_status.0.key', 'completed')
        ->assertJsonPath('data.job_posts_by_category.0.key', 'Electrical')
        ->assertJsonPath('data.reports_by_reason.0.key', 'no_show')
        ->assertJsonPath('data.trend.0.bookings', 1);
});

it('validates analytics date ranges', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $this->actingAs($admin)
        ->getJson('/api/admin/report-analytics?date_from=2026-10-15&date_to=2026-10-14')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date_to']);

    $this->actingAs($admin)
        ->getJson('/api/admin/report-analytics?date_to='.today()->addDay()->toDateString())
        ->assertOk()
        ->assertJsonPath('data.period.date_to', today()->addDay()->toDateString());

    $this->actingAs($admin)
        ->getJson('/api/admin/report-analytics?date_to='.today()->addDays(2)->toDateString())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date_to']);
});

it('streams all supported report exports as CSV and rejects unknown exports', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $user = User::factory()->create([
        'role' => 'client',
        'name' => '=2+2',
        'email' => 'analytics-export@example.com',
    ]);
    $headers = [
        'users' => ['User ID', 'Name', 'Role', 'Active', 'Barangay', 'Email Verified At', 'Created At'],
        'bookings' => ['Booking ID', 'Booking Code', 'Status', 'Client', 'Worker', 'Service Category', 'Scheduled At', 'Started At', 'Completed At', 'Cancelled By', 'Created At'],
        'job-posts' => ['Job Post ID', 'Title', 'Worker', 'Service Category', 'Rate Amount', 'Rate Type', 'Available', 'Active', 'Created At'],
        'reports' => ['Report ID', 'Reason', 'Status', 'Reporter', 'Reported User', 'Booking Code', 'Resolution Action', 'Resolved At', 'Created At'],
        'reviews' => ['Review ID', 'Booking Code', 'Reviewer', 'Reviewed User', 'Score', 'Created At'],
        'verifications' => ['Worker Profile ID', 'Worker', 'Verification Status', 'Trust Tier', 'Average Rating', 'Total Reviews', 'Completed Jobs', 'Verified At', 'Profile Created At'],
    ];

    foreach ($headers as $type => $expectedHeaders) {
        $response = $this->actingAs($admin)
            ->get("/api/admin/report-analytics/exports/{$type}?date_from=".today()->toDateString())
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $firstLine = explode("\n", $content, 2)[0];

        expect($content)->toStartWith("\xEF\xBB\xBF")
            ->and(str_getcsv(substr($firstLine, 3)))->toBe($expectedHeaders);
    }

    $usersCsv = $this->actingAs($admin)
        ->get('/api/admin/report-analytics/exports/users')
        ->assertOk()
        ->streamedContent();

    expect($usersCsv)
        ->toContain('"User ID",Name,Role,Active,Barangay')
        ->toContain("'=2+2")
        ->not->toContain('analytics-export@example.com');

    $this->actingAs($admin)
        ->get('/api/admin/report-analytics/exports/secrets')
        ->assertNotFound();

    $this->actingAs(makeBookingClient())
        ->getJson('/api/admin/report-analytics')
        ->assertForbidden();
});
