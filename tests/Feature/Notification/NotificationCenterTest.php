<?php

use App\Models\HanapbuhayNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ══════════════════════════════════════════════════════════════════════════════
// GET /api/notifications  (notification center list)
// ══════════════════════════════════════════════════════════════════════════════

it('returns 401 when unauthenticated', function () {
    $this->getJson('/api/notifications')->assertStatus(401);
});

it('returns paginated notifications for the authenticated user', function () {
    $user = makeBookingClient();
    HanapbuhayNotification::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson('/api/notifications')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'notifications' => [['id', 'title', 'body', 'type', 'is_read', 'created_at']],
                'unread_count',
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ]);
});

it('returns only the authenticated user\'s notifications', function () {
    $user1 = makeBookingClient();
    $user2 = makeBookingClient();

    HanapbuhayNotification::factory()->count(2)->create(['user_id' => $user1->id]);
    HanapbuhayNotification::factory()->count(3)->create(['user_id' => $user2->id]);

    $response = $this->actingAs($user1)
        ->getJson('/api/notifications')
        ->assertStatus(200);

    expect($response->json('data.pagination.total'))->toBe(2);
});

it('unread_count reflects only unread notifications', function () {
    $user = makeBookingClient();

    HanapbuhayNotification::factory()->count(3)->create(['user_id' => $user->id, 'is_read' => false]);
    HanapbuhayNotification::factory()->read()->count(2)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->getJson('/api/notifications')
        ->assertStatus(200);

    expect($response->json('data.unread_count'))->toBe(3);
    expect($response->json('data.pagination.total'))->toBe(5);
});

it('returns empty list with zero unread when user has no notifications', function () {
    $user = makeBookingClient();

    $response = $this->actingAs($user)
        ->getJson('/api/notifications')
        ->assertStatus(200);

    expect($response->json('data.notifications'))->toBeEmpty();
    expect($response->json('data.unread_count'))->toBe(0);
});

it('notifications are returned most recent first', function () {
    $user = makeBookingClient();

    $older = HanapbuhayNotification::factory()->create([
        'user_id'    => $user->id,
        'created_at' => now()->subMinutes(10),
    ]);
    $newer = HanapbuhayNotification::factory()->create([
        'user_id'    => $user->id,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/notifications')->assertStatus(200);

    $ids = collect($response->json('data.notifications'))->pluck('id')->values()->all();
    expect($ids[0])->toBe($newer->id);
    expect($ids[1])->toBe($older->id);
});

// ══════════════════════════════════════════════════════════════════════════════
// POST /api/notifications/{id}/read
// ══════════════════════════════════════════════════════════════════════════════

it('marks a single notification as read', function () {
    $user         = makeBookingClient();
    $notification = HanapbuhayNotification::factory()->create([
        'user_id' => $user->id,
        'is_read' => false,
    ]);

    $this->actingAs($user)
        ->postJson("/api/notifications/{$notification->id}/read")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Notification marked as read.');

    expect($notification->fresh()->is_read)->toBeTrue();
    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marking an already-read notification is idempotent (returns 200)', function () {
    $user         = makeBookingClient();
    $notification = HanapbuhayNotification::factory()->read()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson("/api/notifications/{$notification->id}/read")
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});

it('returns 404 when marking another user\'s notification as read', function () {
    $user1        = makeBookingClient();
    $user2        = makeBookingClient();
    $notification = HanapbuhayNotification::factory()->create(['user_id' => $user2->id]);

    $this->actingAs($user1)
        ->postJson("/api/notifications/{$notification->id}/read")
        ->assertStatus(404);
});

it('returns 404 for a non-existent notification id', function () {
    $user = makeBookingClient();

    $this->actingAs($user)
        ->postJson('/api/notifications/99999/read')
        ->assertStatus(404);
});

it('returns 401 when unauthenticated on mark read', function () {
    $this->postJson('/api/notifications/1/read')->assertStatus(401);
});

// ══════════════════════════════════════════════════════════════════════════════
// POST /api/notifications/read-all
// ══════════════════════════════════════════════════════════════════════════════

it('marks all unread notifications as read', function () {
    $user = makeBookingClient();

    HanapbuhayNotification::factory()->count(4)->create([
        'user_id' => $user->id,
        'is_read' => false,
    ]);

    $this->actingAs($user)
        ->postJson('/api/notifications/read-all')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'All notifications marked as read.');

    $unread = HanapbuhayNotification::where('user_id', $user->id)
        ->where('is_read', false)
        ->count();

    expect($unread)->toBe(0);
});

it('read-all only affects the current user\'s notifications', function () {
    $user1 = makeBookingClient();
    $user2 = makeBookingClient();

    HanapbuhayNotification::factory()->count(2)->create(['user_id' => $user1->id, 'is_read' => false]);
    HanapbuhayNotification::factory()->count(3)->create(['user_id' => $user2->id, 'is_read' => false]);

    $this->actingAs($user1)->postJson('/api/notifications/read-all')->assertStatus(200);

    // user2's notifications must remain unread
    $user2Unread = HanapbuhayNotification::where('user_id', $user2->id)
        ->where('is_read', false)
        ->count();

    expect($user2Unread)->toBe(3);
});

it('read-all with no unread notifications returns 200 with no side effects', function () {
    $user = makeBookingClient();

    HanapbuhayNotification::factory()->read()->count(2)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/api/notifications/read-all')
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});

it('returns 401 when unauthenticated on read-all', function () {
    $this->postJson('/api/notifications/read-all')->assertStatus(401);
});
