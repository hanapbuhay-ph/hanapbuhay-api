<?php

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

const RESET_PASSWORD_URL = '/api/auth/password/reset';

// Exactly 64 characters
const VALID_RESET_TOKEN = 'valid-reset-token-64-chars-long-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

function makeValidResetOtp(User $user, array $overrides = []): OtpCode
{
    return OtpCode::create(array_merge([
        'email'       => $user->email,
        'code'        => '739201',
        'type'        => 'password_reset',
        'expires_at'  => Carbon::now()->addMinutes(10),
        'used_at'     => null,
        'reset_token' => VALID_RESET_TOKEN,
    ], $overrides));
}

it('returns 200 and resets the password for valid input', function () {
    $user = User::factory()->create(['email' => 'juan@email.com']);
    makeValidResetOtp($user);

    $response = $this->postJson(RESET_PASSWORD_URL, [
        'email'                 => 'juan@email.com',
        'reset_token'           => VALID_RESET_TOKEN,
        'password'              => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'Password reset successfully. Please log in.',
    ]);
});

it('actually updates the password in the database', function () {
    $user = User::factory()->create([
        'email'    => 'juan@email.com',
        'password' => Hash::make('oldpassword'),
    ]);
    makeValidResetOtp($user);

    $this->postJson(RESET_PASSWORD_URL, [
        'email'                 => 'juan@email.com',
        'reset_token'           => VALID_RESET_TOKEN,
        'password'              => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $user->refresh();

    expect(Hash::check('newpassword123', $user->password))->toBeTrue()
        ->and(Hash::check('oldpassword', $user->password))->toBeFalse();
});

it('marks the OTP as used after a successful reset', function () {
    $user = User::factory()->create(['email' => 'juan@email.com']);
    $otp  = makeValidResetOtp($user);

    $this->postJson(RESET_PASSWORD_URL, [
        'email'                 => 'juan@email.com',
        'reset_token'           => VALID_RESET_TOKEN,
        'password'              => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $otp->refresh();

    expect($otp->used_at)->not->toBeNull();
});

it('revokes all Sanctum tokens after a successful reset', function () {
    $user = User::factory()->create(['email' => 'juan@email.com']);
    makeValidResetOtp($user);

    $user->createToken('device-1');
    $user->createToken('device-2');

    expect($user->tokens()->count())->toBe(2);

    $this->postJson(RESET_PASSWORD_URL, [
        'email'                 => 'juan@email.com',
        'reset_token'           => VALID_RESET_TOKEN,
        'password'              => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    expect($user->tokens()->count())->toBe(0);
});

it('returns 422 for a wrong reset_token', function () {
    $user = User::factory()->create(['email' => 'juan@email.com']);
    makeValidResetOtp($user);

    $response = $this->postJson(RESET_PASSWORD_URL, [
        'email'                 => 'juan@email.com',
        'reset_token'           => 'wrong-token',
        'password'              => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertUnprocessable()->assertJson([
        'success' => false,
        'message' => 'Invalid or expired reset token.',
    ]);
});

it('returns 422 when the OTP is expired', function () {
    $user = User::factory()->create(['email' => 'juan@email.com']);
    makeValidResetOtp($user, ['expires_at' => Carbon::now()->subMinute()]);

    $response = $this->postJson(RESET_PASSWORD_URL, [
        'email'                 => 'juan@email.com',
        'reset_token'           => VALID_RESET_TOKEN,
        'password'              => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertUnprocessable()->assertJson([
        'success' => false,
        'message' => 'Invalid or expired reset token.',
    ]);
});

it('returns 422 when the OTP is already used', function () {
    $user = User::factory()->create(['email' => 'juan@email.com']);
    makeValidResetOtp($user, ['used_at' => Carbon::now()->subMinute()]);

    $response = $this->postJson(RESET_PASSWORD_URL, [
        'email'                 => 'juan@email.com',
        'reset_token'           => VALID_RESET_TOKEN,
        'password'              => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertUnprocessable()->assertJson([
        'success' => false,
        'message' => 'Invalid or expired reset token.',
    ]);
});

it('returns 422 when the email is not in the database', function () {
    $response = $this->postJson(RESET_PASSWORD_URL, [
        'email'                 => 'nobody@email.com',
        'reset_token'           => VALID_RESET_TOKEN,
        'password'              => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertUnprocessable()->assertJson([
        'success' => false,
        'message' => 'Invalid or expired reset token.',
    ]);
});

it('returns 422 when the password field is missing', function () {
    $response = $this->postJson(RESET_PASSWORD_URL, [
        'email'       => 'juan@email.com',
        'reset_token' => 'some-token',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('returns 422 when the password confirmation does not match', function () {
    $response = $this->postJson(RESET_PASSWORD_URL, [
        'email'                 => 'juan@email.com',
        'reset_token'           => 'some-token',
        'password'              => 'newpassword123',
        'password_confirmation' => 'differentpassword',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('returns 422 when the password is shorter than 8 characters', function () {
    $response = $this->postJson(RESET_PASSWORD_URL, [
        'email'                 => 'juan@email.com',
        'reset_token'           => 'some-token',
        'password'              => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});
