<?php

use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('issues a login verification code that expires in five minutes', function () {
    Mail::fake();
    Carbon::setTestNow(Carbon::parse('2026-10-14 12:00:00'));

    $admin = User::factory()->create([
        'role' => 'admin',
        'password' => 'password',
        'two_factor_enabled' => true,
    ]);

    $this->postJson('/api/auth/admin/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('data.requiresOtp', true)
        ->assertJsonPath('data.otp_expires_in_seconds', OtpCode::VALIDITY_SECONDS);

    expect(OtpCode::where('email', $admin->email)
        ->where('type', 'login_2fa')
        ->firstOrFail()
        ->expires_at->toDateTimeString())->toBe('2026-10-14 12:05:00');

    Mail::assertQueued(OtpMail::class, fn ($mail) => str_contains($mail->render(), '5 minutes'));
    Carbon::setTestNow();
});

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
        'expires_at' => now()->addMinutes(OtpCode::VALIDITY_MINUTES),
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
        'expires_at' => now()->addMinutes(OtpCode::VALIDITY_MINUTES),
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

it('sends an admin Google login OTP when two-factor authentication is enabled', function () {
    Mail::fake();
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
        'two_factor_enabled' => true,
    ]);

    $challenge = $this->postJson('/api/auth/admin/google', [
        'google_token' => 'valid-google-token',
        'device_name' => 'test-device',
    ])->assertOk()
        ->assertJsonPath('data.requiresOtp', true)
        ->assertJsonPath('data.user.email', $admin->email)
        ->assertJsonPath('data.otp_expires_in_seconds', OtpCode::VALIDITY_SECONDS);

    expect(OtpCode::where('email', $admin->email)
        ->where('type', 'login_2fa')
        ->count())->toBe(1)
        ->and($admin->tokens()->count())->toBe(0);

    Mail::assertQueued(OtpMail::class, fn (OtpMail $mail) => $mail->hasTo($admin->email));

    $otp = OtpCode::where('email', $admin->email)
        ->where('type', 'login_2fa')
        ->firstOrFail();

    $this->postJson('/api/auth/admin/login/verify-otp', [
        'email' => $admin->email,
        'code' => $otp->code,
        'device_name' => 'test-device',
    ])->assertOk()
        ->assertJsonPath('data.user.email', $admin->email)
        ->assertJsonStructure(['data' => ['token']]);

    expect($challenge->json('data.requiresOtp'))->toBeTrue()
        ->and($admin->tokens()->count())->toBe(1);
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
