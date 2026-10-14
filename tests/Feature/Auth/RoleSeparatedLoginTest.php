<?php

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('rejects admin roles from mobile password login but allows the admin portal', function (string $role) {
    $admin = User::factory()->create([
        'role' => $role,
        'password' => 'password',
        'two_factor_enabled' => false,
    ]);

    $payload = [
        'email' => $admin->email,
        'password' => 'password',
        'role' => 'client',
    ];

    $this->postJson('/api/auth/login', $payload)->assertUnauthorized();
    expect($admin->tokens()->count())->toBe(0);

    $this->postJson('/api/auth/admin/login', $payload)
        ->assertOk()
        ->assertJsonPath('data.user.role', $role);

    expect($admin->tokens()->count())->toBe(1);
})->with(['admin', 'super_admin']);

it('only verifies admin login OTPs through the admin portal', function () {
    $admin = User::factory()->create([
        'role' => 'super_admin',
        'two_factor_enabled' => true,
    ]);

    OtpCode::create([
        'email' => $admin->email,
        'code' => '123456',
        'type' => 'login_2fa',
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->postJson('/api/auth/login/verify-otp', [
        'email' => $admin->email,
        'code' => '123456',
    ])->assertStatus(422);

    expect($admin->tokens()->count())->toBe(0);

    $this->postJson('/api/auth/admin/login/verify-otp', [
        'email' => $admin->email,
        'code' => '123456',
    ])->assertOk()
        ->assertJsonPath('data.user.role', 'super_admin');

    expect($admin->tokens()->count())->toBe(1);
});

it('does not issue a mobile token when an admin uses email verification', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => null,
    ]);

    OtpCode::create([
        'email' => $admin->email,
        'code' => '123456',
        'type' => 'email_verification',
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->postJson('/api/auth/email/verify', [
        'email' => $admin->email,
        'code' => '123456',
    ])->assertForbidden();

    expect($admin->tokens()->count())->toBe(0);
});

it('only allows admin Google accounts through the admin portal endpoint', function () {
    config(['services.google.client_id' => 'test-google-client']);
    Http::fake([
        'oauth2.googleapis.com/tokeninfo*' => Http::response([
            'sub' => 'google-admin-subject',
            'email' => 'admin@example.com',
            'email_verified' => 'true',
            'aud' => 'test-google-client',
        ]),
    ]);

    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'google_id' => 'google-admin-subject',
        'role' => 'admin',
        'two_factor_enabled' => false,
    ]);

    $payload = [
        'google_token' => 'valid-google-token',
        'device_name' => 'test-device',
        'role' => 'client',
    ];

    $this->postJson('/api/auth/google', $payload)->assertForbidden();
    expect($admin->tokens()->count())->toBe(0);

    $this->postJson('/api/auth/admin/google', $payload)
        ->assertOk()
        ->assertJsonPath('data.user.role', 'admin');

    expect($admin->tokens()->count())->toBe(1);
});

it('does not allow non-admin users to use admin portal login', function () {
    $client = User::factory()->create([
        'role' => 'client',
        'password' => 'password',
    ]);

    $this->postJson('/api/auth/admin/login', [
        'email' => $client->email,
        'password' => 'password',
    ])->assertUnauthorized();

    expect($client->tokens()->count())->toBe(0);
});
