<?php

use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

const RESEND_OTP_URL = '/api/auth/email/resend-otp';

beforeEach(function () {
    Mail::fake();
    RateLimiter::clear('resend-otp:juan@email.com');
});

it('resends OTP for a valid unverified email', function () {
    $user = User::factory()->create([
        'email'             => 'juan@email.com',
        'email_verified_at' => null,
    ]);

    OtpCode::create([
        'email'      => $user->email,
        'code'       => '123456',
        'type'       => 'email_verification',
        'expires_at' => Carbon::now()->addMinutes(10),
        'used_at'    => null,
    ]);

    $response = $this->postJson(RESEND_OTP_URL, ['email' => 'juan@email.com']);

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'Verification code resent to your email.',
    ]);

    // Old OTP must be invalidated
    $this->assertDatabaseMissing('otp_codes', [
        'email'   => 'juan@email.com',
        'code'    => '123456',
        'used_at' => null,
    ]);

    // New OTP must exist
    $this->assertDatabaseHas('otp_codes', [
        'email'   => 'juan@email.com',
        'type'    => 'email_verification',
        'used_at' => null,
    ]);

    Mail::assertSent(OtpMail::class, fn (OtpMail $mail) => $mail->hasTo('juan@email.com'));
});

it('returns 200 when email is not registered', function () {
    $response = $this->postJson(RESEND_OTP_URL, ['email' => 'juan@email.com']);

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'Verification code resent to your email.',
    ]);

    Mail::assertNothingSent();
});

it('returns 200 with already-verified message when email is verified', function () {
    User::factory()->create([
        'email'             => 'juan@email.com',
        'email_verified_at' => Carbon::now(),
    ]);

    $response = $this->postJson(RESEND_OTP_URL, ['email' => 'juan@email.com']);

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'Your email is already verified. Please log in.',
    ]);

    Mail::assertNothingSent();
});

it('returns 422 when email field is missing', function () {
    $response = $this->postJson(RESEND_OTP_URL, []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when email format is invalid', function () {
    $response = $this->postJson(RESEND_OTP_URL, ['email' => 'not-an-email']);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 429 when rate limit is exceeded', function () {
    User::factory()->create([
        'email'             => 'juan@email.com',
        'email_verified_at' => null,
    ]);

    // Exhaust the 3 allowed attempts
    foreach (range(1, 3) as $i) {
        $this->postJson(RESEND_OTP_URL, ['email' => 'juan@email.com']);
    }

    // 4th request must be rejected
    $response = $this->postJson(RESEND_OTP_URL, ['email' => 'juan@email.com']);

    $response->assertStatus(429)->assertJson([
        'success' => false,
        'message' => 'Too many requests. Please wait before requesting another code.',
    ]);
});
