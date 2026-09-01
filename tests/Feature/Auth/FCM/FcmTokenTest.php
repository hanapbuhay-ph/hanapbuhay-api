<?php

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ══════════════════════════════════════════════════════════════════════════════
// POST /api/user/fcm-token  (spec URL alias for /notifications/register-device)
// ══════════════════════════════════════════════════════════════════════════════

it('registers a device token via spec URL /api/user/fcm-token', function () {
    $user = makeBookingClient();

    $this->actingAs($user)
        ->postJson('/api/user/fcm-token', [
            'fcm_token'   => 'test-token-abc',
            'device_type' => 'android',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Device registered successfully.');

    $this->assertDatabaseHas('device_tokens', [
        'user_id'   => $user->id,
        'fcm_token' => 'test-token-abc',
    ]);
});

it('calling /user/fcm-token and /notifications/register-device with same token results in one DB row', function () {
    $user    = makeBookingClient();
    $payload = ['fcm_token' => 'shared-token', 'device_type' => 'ios'];

    $this->actingAs($user)->postJson('/api/user/fcm-token', $payload)->assertStatus(200);
    $this->actingAs($user)->postJson('/api/notifications/register-device', $payload)->assertStatus(200);

    expect(DeviceToken::where('user_id', $user->id)->where('fcm_token', 'shared-token')->count())->toBe(1);
});

it('returns 422 when fcm_token is missing on /user/fcm-token', function () {
    $user = makeBookingClient();

    $this->actingAs($user)
        ->postJson('/api/user/fcm-token', ['device_type' => 'android'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['fcm_token']);
});

it('returns 401 when unauthenticated on /user/fcm-token', function () {
    $this->postJson('/api/user/fcm-token', [])->assertStatus(401);
});
