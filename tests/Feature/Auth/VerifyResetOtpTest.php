<?php

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

const VERIFY_RESET_OTP_URL = '/api/auth/password/verify-otp';

function makeResetOtp(string $email, array $overrides = []): OtpCode
{
    return OtpCode::create(array_merge([
        'email'      => $email,
        'code'       => '739201',
        'type'       => 'password_reset',
        'expires_at' => Carbon::now()->addMinutes(10),
        'used_at'    => null,
        'reset_token' => null,
    ], $overrides));
}

it('returns 200 with reset_token for valid email and correct code', function () {
    User::factory()->create(['email' => 'juan@email.com']);
    makeResetOtp('juan@email.com');

    $response = $this->postJson(VERIFY_RESET_OTP_URL, [
        'email' => 'juan@email.com',
        'code'  => '739201',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'Code verified. You can now reset your password.',
    ]);

    expect($response->json('data.reset_token'))->toBeString()->toHaveLength(64);
});

it('returns 422 for a wrong code', function () {
    User::factory()->create(['email' => 'juan@email.com']);
    makeResetOtp('juan@email.com');

    $response = $this->postJson(VERIFY_RESET_OTP_URL, [
        'email' => 'juan@email.com',
        'code'  => '000000',
    ]);

    $response->assertUnprocessable()->assertJson([
        'success' => false,
        'message' => 'Invalid or expired reset code.',
    ]);
});

it('returns 422 for an expired OTP', function () {
    User::factory()->create(['email' => 'juan@email.com']);
    makeResetOtp('juan@email.com', ['expires_at' => Carbon::now()->subMinute()]);

    $response = $this->postJson(VERIFY_RESET_OTP_URL, [
        'email' => 'juan@email.com',
        'code'  => '739201',
    ]);

    $response->assertUnprocessable()->assertJson([
        'success' => false,
        'message' => 'Invalid or expired reset code.',
    ]);
});

it('returns 422 for an already used OTP', function () {
    User::factory()->create(['email' => 'juan@email.com']);
    makeResetOtp('juan@email.com', ['used_at' => Carbon::now()->subMinute()]);

    $response = $this->postJson(VERIFY_RESET_OTP_URL, [
        'email' => 'juan@email.com',
        'code'  => '739201',
    ]);

    $response->assertUnprocessable()->assertJson([
        'success' => false,
        'message' => 'Invalid or expired reset code.',
    ]);
});

it('returns 422 when email is not in the database', function () {
    $response = $this->postJson(VERIFY_RESET_OTP_URL, [
        'email' => 'juan@email.com',
        'code'  => '739201',
    ]);

    $response->assertUnprocessable()->assertJson([
        'success' => false,
        'message' => 'Invalid or expired reset code.',
    ]);
});

it('returns 422 when email field is missing', function () {
    $response = $this->postJson(VERIFY_RESET_OTP_URL, ['code' => '739201']);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when code field is missing', function () {
    $response = $this->postJson(VERIFY_RESET_OTP_URL, ['email' => 'juan@email.com']);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('stores the reset_token on the otp_codes row after successful verification', function () {
    User::factory()->create(['email' => 'juan@email.com']);
    $otp = makeResetOtp('juan@email.com');

    $response = $this->postJson(VERIFY_RESET_OTP_URL, [
        'email' => 'juan@email.com',
        'code'  => '739201',
    ]);

    $resetToken = $response->json('data.reset_token');

    $this->assertDatabaseHas('otp_codes', [
        'id'          => $otp->id,
        'reset_token' => $resetToken,
        'used_at'     => null, // must NOT be marked used yet
    ]);
});
