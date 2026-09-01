<?php

use App\Models\Announcement;
use App\Models\HanapbuhayNotification;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSettingsAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'is_active' => true, 'email_verified_at' => now()]);
}

// ══════════════════════════════════════════════════════════════════════════════
// GET /api/admin/settings  (spec §K17)
// ══════════════════════════════════════════════════════════════════════════════

it('GET /admin/settings returns all four required sections', function () {
    $admin = makeSettingsAdmin();

    $this->actingAs($admin)
        ->getJson('/api/admin/settings')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Settings retrieved.')
        ->assertJsonStructure([
            'data' => [
                'service_categories',
                'report_reasons',
                'notification_templates',
            ],
        ]);
});

it('settings report_reasons contains the expected enum values', function () {
    $admin = makeSettingsAdmin();

    $response = $this->actingAs($admin)->getJson('/api/admin/settings')->assertStatus(200);

    $reasons = $response->json('data.report_reasons');
    expect($reasons)->toContain('misconduct');
    expect($reasons)->toContain('no_show');
    expect($reasons)->toContain('other');
});

it('settings active_announcement is null when no active announcement exists', function () {
    $admin = makeSettingsAdmin();

    $response = $this->actingAs($admin)->getJson('/api/admin/settings')->assertStatus(200);

    expect($response->json('data.active_announcement'))->toBeNull();
});

it('settings active_announcement returns the latest active announcement', function () {
    $admin = makeSettingsAdmin();

    Announcement::create([
        'posted_by'  => $admin->id,
        'title'      => 'System Maintenance',
        'body'       => 'We will be down.',
        'expires_at' => now()->addDays(7),
        'is_active'  => true,
    ]);

    $response = $this->actingAs($admin)->getJson('/api/admin/settings')->assertStatus(200);

    expect($response->json('data.active_announcement.title'))->toBe('System Maintenance');
});

it('settings active_announcement is null when announcement is expired', function () {
    $admin = makeSettingsAdmin();

    Announcement::create([
        'posted_by'  => $admin->id,
        'title'      => 'Old Announcement',
        'body'       => 'Expired.',
        'expires_at' => now()->subDay(), // already expired
        'is_active'  => true,
    ]);

    $response = $this->actingAs($admin)->getJson('/api/admin/settings')->assertStatus(200);

    expect($response->json('data.active_announcement'))->toBeNull();
});

it('returns 403 for non-admin on GET /admin/settings', function () {
    $client = makeBookingClient();

    $this->actingAs($client)->getJson('/api/admin/settings')->assertStatus(403);
});

it('returns 401 when unauthenticated on GET /admin/settings', function () {
    $this->getJson('/api/admin/settings')->assertStatus(401);
});

// ══════════════════════════════════════════════════════════════════════════════
// POST /api/admin/settings — action=post_announcement
// ══════════════════════════════════════════════════════════════════════════════

it('admin can post an announcement via POST /admin/settings', function () {
    $admin = makeSettingsAdmin();

    $this->actingAs($admin)
        ->postJson('/api/admin/settings', [
            'action'     => 'post_announcement',
            'title'      => 'Platform Update',
            'body'       => 'New features have been added to HanapBuhay.',
            'expires_at' => now()->addDays(14)->toDateString(),
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Setting updated.')
        ->assertJsonStructure(['data' => ['announcement' => ['id', 'title', 'body', 'expires_at']]]);

    $this->assertDatabaseHas('announcements', [
        'title'     => 'Platform Update',
        'posted_by' => $admin->id,
    ]);
});

it('posting an announcement creates in-app notifications for all active users', function () {
    $admin  = makeSettingsAdmin();
    $client = makeBookingClient();

    $this->actingAs($admin)
        ->postJson('/api/admin/settings', [
            'action' => 'post_announcement',
            'title'  => 'Big News!',
            'body'   => 'Something great is happening.',
        ]);

    expect(
        HanapbuhayNotification::where('user_id', $client->id)
            ->where('type', 'system_announcement')
            ->exists()
    )->toBeTrue();
});

it('posting announcement writes an audit log', function () {
    $admin = makeSettingsAdmin();

    $this->actingAs($admin)
        ->postJson('/api/admin/settings', [
            'action' => 'post_announcement',
            'title'  => 'Audit Test',
            'body'   => 'Testing audit log.',
        ]);

    $this->assertDatabaseHas('admin_audit_logs', [
        'admin_id' => $admin->id,
        'action'   => 'post_announcement',
    ]);
});

it('returns 422 when action=post_announcement is missing title', function () {
    $admin = makeSettingsAdmin();

    $this->actingAs($admin)
        ->postJson('/api/admin/settings', [
            'action' => 'post_announcement',
            'body'   => 'Missing title.',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title']);
});

it('returns 422 when expires_at is in the past', function () {
    $admin = makeSettingsAdmin();

    $this->actingAs($admin)
        ->postJson('/api/admin/settings', [
            'action'     => 'post_announcement',
            'title'      => 'Past',
            'body'       => 'Past expiry.',
            'expires_at' => now()->subDay()->toDateString(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['expires_at']);
});

// ══════════════════════════════════════════════════════════════════════════════
// POST /api/admin/settings — action=add_category
// ══════════════════════════════════════════════════════════════════════════════

it('admin can add a category via POST /admin/settings action=add_category', function () {
    $admin = makeSettingsAdmin();

    $this->actingAs($admin)
        ->postJson('/api/admin/settings', [
            'action' => 'add_category',
            'name'   => 'Tailoring',
            'icon'   => 'tailoring',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Setting updated.');

    $this->assertDatabaseHas('service_categories', ['name' => 'Tailoring']);
});

it('returns 422 when action is invalid on POST /admin/settings', function () {
    $admin = makeSettingsAdmin();

    $this->actingAs($admin)
        ->postJson('/api/admin/settings', ['action' => 'nuke_everything'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['action']);
});

it('returns 422 when action field is missing on POST /admin/settings', function () {
    $admin = makeSettingsAdmin();

    $this->actingAs($admin)
        ->postJson('/api/admin/settings', ['title' => 'No action'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['action']);
});

it('returns 403 for non-admin on POST /admin/settings', function () {
    $client = makeBookingClient();

    $this->actingAs($client)
        ->postJson('/api/admin/settings', ['action' => 'post_announcement'])
        ->assertStatus(403);
});
