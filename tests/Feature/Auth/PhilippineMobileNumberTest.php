<?php

use App\Models\Barangay;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('accepts and stores Philippine mobile numbers in national format during registration', function (string $mobileNumber) {
    $barangay = Barangay::factory()->create();

    $response = $this->postJson('/api/auth/register', [
        'name' => 'Philippine User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'mobile_number' => $mobileNumber,
        'role' => 'client',
        'barangay_id' => $barangay->id,
    ])->assertCreated()
        ->assertJsonPath('data.otp_expires_in_seconds', OtpCode::VALIDITY_SECONDS);

    expect(User::findOrFail($response->json('data.user.id'))->mobile_number)
        ->toBe('09171234567');
})->with([
    'national number' => '09171234567',
    'national formatted number' => '0917 123 4567',
    'international formatted number' => '+63 917 123 4567',
]);

it('rejects non-Philippine or malformed mobile numbers during registration', function (string $mobileNumber) {
    $barangay = Barangay::factory()->create();

    $this->postJson('/api/auth/register', [
        'name' => 'Philippine User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'mobile_number' => $mobileNumber,
        'role' => 'client',
        'barangay_id' => $barangay->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('mobile_number');
})->with([
    'too short' => '0917123456',
    'too long' => '091712345678',
    'landline' => '0281234567',
    'foreign number' => '+14155552671',
    'invalid country code' => '+64 21 123 4567',
]);

it('normalizes international Philippine numbers when updating a profile', function () {
    $user = User::factory()->create(['mobile_number' => '09171234567']);

    $this->actingAs($user)
        ->postJson('/api/user/profile', ['mobile_number' => '+63 917-765-4321'])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($user->fresh()->mobile_number)->toBe('09177654321');
});

it('rejects a non-Philippine mobile number when updating a profile', function () {
    $user = User::factory()->create(['mobile_number' => '09171234567']);

    $this->actingAs($user)
        ->postJson('/api/user/profile', ['mobile_number' => '+14155552671'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('mobile_number');
});

it('validates and normalizes the mobile number when completing a Google profile', function () {
    $user = User::factory()->create([
        'role' => null,
        'is_google_account' => true,
    ]);
    $barangay = Barangay::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/auth/google/complete-profile', [
            'name' => 'Google User',
            'mobile_number' => '+63 917 123 4567',
            'role' => 'client',
            'barangay_id' => $barangay->id,
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($user->fresh()->mobile_number)->toBe('09171234567');

    $incompleteUser = User::factory()->create([
        'role' => null,
        'is_google_account' => true,
    ]);

    $this->actingAs($incompleteUser)
        ->postJson('/api/auth/google/complete-profile', [
            'name' => 'Google User',
            'mobile_number' => '+14155552671',
            'role' => 'client',
            'barangay_id' => $barangay->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('mobile_number');
});

it('normalizes and validates mobile numbers during admin user creation', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $barangay = Barangay::factory()->create();

    $response = $this->actingAs($admin)
        ->postJson('/api/admin/users', [
            'name' => 'New Client',
            'email' => 'new-client-mobile@example.test',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'mobile_number' => '+63 917 123 4567',
            'role' => 'client',
            'barangay_id' => $barangay->id,
        ])
        ->assertCreated();

    expect(User::findOrFail($response->json('data.id'))->mobile_number)
        ->toBe('09171234567');

    $this->actingAs($admin)
        ->postJson('/api/admin/users', [
            'name' => 'Invalid Client',
            'email' => 'invalid-client-mobile@example.test',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'mobile_number' => '+14155552671',
            'role' => 'client',
            'barangay_id' => $barangay->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('mobile_number');
});
