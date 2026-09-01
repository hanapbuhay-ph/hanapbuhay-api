<?php

use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

const FORGOT_PASSWORD_URL = '/api/auth/password/forgot';

beforeEach(function () {
    Mail::fake();
    RateLimiter::clear('forgot-password:juan@email.com');
});

it('returns 200 with success message for a registered email', function () {
    User::factory()->create(['email' => 'juan@email.com']);

    $response = $this->postJson(FORGOT_PASSWORD_URL, ['email' => 'juan@email.com']);

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'If that email is registered, a password reset code has been sent.',
    ]);

    Mail::assertSent(OtpMail::class, fn (OtpMail $mail) => $mail->hasTo('juan@email.com'));
});

it('returns 200 with same success message when email is not registered', function () {
    $response = $this->postJson(FORGOT_PASSWORD_URL, ['email' => 'juan@email.com']);

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'If that email is registered, a password reset code has been sent.',
    ]);

    Mail::assertNothingSent();
});

it('returns 422 when email field is missing', function () {
    $response = $this->postJson(FORGOT_PASSWORD_URL, []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when email format is invalid', function () {
    $response = $this->postJson(FORGOT_PASSWORD_URL, ['email' => 'not-an-email']);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 429 when rate limit is exceeded', function () {
    User::factory()->create(['email' => 'juan@email.com']);

    foreach (range(1, 3) as $i) {
        $this->postJson(FORGOT_PASSWORD_URL, ['email' => 'juan@email.com']);
    }

    $response = $this->postJson(FORGOT_PASSWORD_URL, ['email' => 'juan@email.com']);

    $response->assertStatus(429)->assertJson([
        'success' => false,
        'message' => 'Too many requests. Please wait before trying again.',
    ]);
});

it('creates a password_reset OTP record with correct type and expires_at', function () {
    User::factory()->create(['email' => 'juan@email.com']);

    Carbon::setTestNow(Carbon::parse('2025-01-01 10:00:00'));

    $this->postJson(FORGOT_PASSWORD_URL, ['email' => 'juan@email.com']);

    $otp = OtpCode::where('email', 'juan@email.com')
        ->where('type', 'password_reset')
        ->whereNull('used_at')
        ->first();

    expect($otp)->not->toBeNull()
        ->and($otp->expires_at->toDateTimeString())->toBe('2025-01-01 10:10:00');

    Carbon::setTestNow();
});

it('invalidates previous unused password_reset OTPs when a new one is requested', function () {
    $user = User::factory()->create(['email' => 'juan@email.com']);

    OtpCode::create([
        'email'      => $user->email,
        'code'       => '111111',
        'type'       => 'password_reset',
        'expires_at' => Carbon::now()->addMinutes(10),
        'used_at'    => null,
    ]);

    $this->postJson(FORGOT_PASSWORD_URL, ['email' => 'juan@email.com']);

    // Old OTP must be invalidated
    $this->assertDatabaseMissing('otp_codes', [
        'email'   => 'juan@email.com',
        'code'    => '111111',
        'used_at' => null,
    ]);

    // New OTP must exist
    $this->assertDatabaseHas('otp_codes', [
        'email'   => 'juan@email.com',
        'type'    => 'password_reset',
        'used_at' => null,
    ]);
});
