<?php

use App\Models\AdminNotification;
use App\Models\AdminNotificationRead;
use App\Models\Barangay;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Services\JobPost\JobPostService;
use App\Services\SecurityEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('lists notifications with cursor pagination and per-admin read state', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $otherAdmin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $items = collect(range(1, 3))->map(fn (int $number) => AdminNotification::create([
        'type' => 'new_registration',
        'title' => "Registration {$number}",
        'body' => 'A new account registered.',
    ]));
    AdminNotificationRead::create([
        'admin_notification_id' => $items->first()->id,
        'admin_id' => $admin->id,
        'read_at' => now(),
    ]);

    $firstPage = $this->actingAs($admin)
        ->getJson('/api/admin/notifications?per_page=2')
        ->assertOk()
        ->assertJsonPath('data.unread_count', 2)
        ->assertJsonPath('data.pagination.has_more', true)
        ->assertJsonCount(2, 'data.notifications');

    expect($firstPage->json('data.notifications.1.is_read'))->toBeFalse();
    $cursor = $firstPage->json('data.pagination.next_cursor');

    $this->getJson('/api/admin/notifications?per_page=2&cursor='.urlencode($cursor))
        ->assertOk()
        ->assertJsonCount(1, 'data.notifications');

    $this->actingAs($otherAdmin)
        ->getJson('/api/admin/notifications')
        ->assertJsonPath('data.unread_count', 3);
});

it('marks one notification read for only the current admin', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $otherAdmin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $notification = AdminNotification::create([
        'type' => 'new_report',
        'title' => 'New report',
        'body' => 'A report was submitted.',
    ]);

    $this->actingAs($admin)
        ->postJson("/api/admin/notifications/{$notification->id}/read")
        ->assertOk();

    $this->assertDatabaseHas('admin_notification_reads', [
        'admin_notification_id' => $notification->id,
        'admin_id' => $admin->id,
    ]);
    $this->assertDatabaseMissing('admin_notification_reads', [
        'admin_notification_id' => $notification->id,
        'admin_id' => $otherAdmin->id,
    ]);
});

it('allows only the super admin to delete notifications individually or in bulk', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    $notification = AdminNotification::create([
        'type' => 'security_alert',
        'title' => 'Security alert',
        'body' => 'An event was blocked.',
    ]);

    $this->actingAs($admin)
        ->deleteJson("/api/admin/notifications/{$notification->id}")
        ->assertForbidden();
    $this->actingAs($admin)
        ->deleteJson('/api/admin/notifications')
        ->assertForbidden();
    $this->assertDatabaseHas('admin_notifications', ['id' => $notification->id]);

    $this->actingAs($superAdmin)
        ->deleteJson("/api/admin/notifications/{$notification->id}")
        ->assertOk();
    $this->assertDatabaseHas('admin_audit_logs', [
        'action' => 'admin_notification_deleted',
        'target_id' => $notification->id,
    ]);
    AdminNotification::create([
        'type' => 'new_report',
        'title' => 'New report',
        'body' => 'A report was submitted.',
    ]);
    $this->deleteJson('/api/admin/notifications')
        ->assertOk()
        ->assertJsonPath('data.deleted_count', 1);
    $this->assertDatabaseHas('admin_audit_logs', [
        'action' => 'admin_notifications_cleared',
        'outcome' => 'success',
    ]);
    $this->assertDatabaseCount('admin_notifications', 0);
});

it('creates a security notification after five failed logins from one IP in 15 minutes', function () {
    $logger = app(SecurityEventLogger::class);

    foreach (range(1, 5) as $attempt) {
        $logger->record('login', 'failure', null, null, [], '203.0.113.10');
    }

    $this->assertDatabaseCount('admin_notifications', 1);

    $logger->record('login', 'failure', null, null, [], '203.0.113.10');
    $this->assertDatabaseCount('admin_notifications', 1);
    $this->assertDatabaseHas('admin_audit_logs', [
        'action' => 'login',
        'outcome' => 'failure',
        'ip_address' => '203.0.113.10',
    ]);
});

it('immediately notifies admins of blocked protected-account changes', function () {
    app(SecurityEventLogger::class)->record(
        'protected_account_status_change_denied',
        'failure',
        User::factory()->create(['role' => 'admin']),
        1,
    );

    $this->assertDatabaseHas('admin_notifications', [
        'type' => 'security_alert',
        'title' => 'Protected account access blocked',
    ]);
});

it('notifies admins when a new account registers', function () {
    Mail::fake();
    $barangay = Barangay::factory()->create();

    $response = $this->postJson('/api/auth/register', [
        'name' => 'New Client',
        'email' => 'new-client@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'mobile_number' => '09171234567',
        'role' => 'client',
        'barangay_id' => $barangay->id,
    ])->assertCreated();

    $notification = AdminNotification::where('type', 'new_registration')->firstOrFail();
    expect($notification->data['url'])->toBe('/users/'.$response->json('data.user.id'));
});

it('notifies admins when a worker publishes a job post', function () {
    $worker = User::factory()->create(['role' => 'worker']);
    $profile = WorkerProfile::factory()->create(['user_id' => $worker->id]);
    $category = ServiceCategory::factory()->create();

    $post = app(JobPostService::class)->create($worker, [
        'service_category_id' => $category->id,
        'title' => 'Home repair',
        'description' => 'General repairs.',
        'rate_amount' => 500,
        'rate_type' => 'per_session',
    ]);

    $notification = AdminNotification::where('type', 'new_job_post')->firstOrFail();
    expect($notification->data['url'])->toBe("/job-posts/{$post->id}")
        ->and($profile->id)->toBe($worker->workerProfile->id);
});
