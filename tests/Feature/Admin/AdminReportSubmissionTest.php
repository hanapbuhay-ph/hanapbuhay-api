<?php

use App\Models\Booking;
use App\Models\Report;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeReportSubmissionBooking(): Booking
{
    return Booking::factory()->create([
        'client_id' => makeBookingClient()->id,
        'worker_id' => makeApprovedBookingWorker()->id,
        'service_category_id' => ServiceCategory::factory()->create()->id,
        'status' => 'completed',
    ]);
}

it('allows an admin to submit a report on behalf of a client', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $booking = makeReportSubmissionBooking();

    $response = $this->actingAs($admin)->postJson('/api/admin/reports', [
        'booking_id' => $booking->id,
        'reporter_role' => 'client',
        'reason' => 'misconduct',
        'description' => 'The client reported that the worker acted inappropriately.',
    ])->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Report submitted on behalf of the user.');

    $report = Report::findOrFail($response->json('data.report.id'));
    expect($report->reported_by)->toBe($booking->client_id)
        ->and($report->reported_user)->toBe($booking->worker_id)
        ->and($report->submitted_by_admin_id)->toBe($admin->id);

    $this->assertDatabaseHas('admin_audit_logs', [
        'admin_id' => $admin->id,
        'action' => 'report_submitted_on_behalf',
        'target_type' => 'Report',
        'target_id' => $report->id,
    ]);
    $this->assertDatabaseHas('hanapbuhay_notifications', [
        'user_id' => $booking->client_id,
        'type' => 'report_submitted_on_behalf',
    ]);
    $this->assertDatabaseHas('hanapbuhay_notifications', [
        'user_id' => $booking->worker_id,
        'type' => 'report_received',
    ]);
});

it('allows an admin to submit a report on behalf of a worker against the client', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $booking = makeReportSubmissionBooking();

    $response = $this->actingAs($admin)->postJson('/api/admin/reports', [
        'booking_id' => $booking->id,
        'reporter_role' => 'worker',
        'reason' => 'client_misconduct',
        'description' => 'The worker reported inappropriate conduct by the client.',
    ])->assertCreated();

    $report = Report::findOrFail($response->json('data.report.id'));
    expect($report->reported_by)->toBe($booking->worker_id)
        ->and($report->reported_user)->toBe($booking->client_id)
        ->and($report->submitted_by_admin_id)->toBe($admin->id);
});

it('rejects a report reason that is not enabled for the represented reporting role', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $booking = makeReportSubmissionBooking();

    $this->actingAs($admin)->postJson('/api/admin/reports', [
        'booking_id' => $booking->id,
        'reporter_role' => 'worker',
        'reason' => 'misconduct',
        'description' => 'The worker reported an incident.',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('does not allow a non-admin to submit an admin report', function () {
    $client = makeBookingClient();

    $this->actingAs($client)->postJson('/api/admin/reports', [])
        ->assertForbidden();
});
