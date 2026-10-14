<?php

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('preserves an external profile photo URL when verifying email', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'google-user@example.com',
        'profile_photo_path' => 'https://lh3.googleusercontent.com/profile-photo',
        'is_google_account' => true,
    ]);

    OtpCode::create([
        'email' => $user->email,
        'code' => '123456',
        'type' => 'email_verification',
        'expires_at' => Carbon::now()->addMinutes(10),
    ]);

    $this->postJson('/api/auth/email/verify', [
        'email' => $user->email,
        'code' => '123456',
    ])->assertOk()->assertJsonPath(
        'data.user.profile_photo_url',
        'https://lh3.googleusercontent.com/profile-photo',
    );
});

it('returns a storage URL for a stored profile photo when verifying email', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'manual-user@example.com',
        'profile_photo_path' => 'profile-photos/avatar.jpg',
    ]);

    OtpCode::create([
        'email' => $user->email,
        'code' => '123456',
        'type' => 'email_verification',
        'expires_at' => Carbon::now()->addMinutes(10),
    ]);

    $this->postJson('/api/auth/email/verify', [
        'email' => $user->email,
        'code' => '123456',
    ])->assertOk()->assertJsonPath(
        'data.user.profile_photo_url',
        asset('storage/profile-photos/avatar.jpg'),
    );
});
