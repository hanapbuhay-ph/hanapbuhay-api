<?php

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ══════════════════════════════════════════════════════════════════════════════
// UPDATE PROFILE
// ══════════════════════════════════════════════════════════════════════════════

it('authenticated user can update their name', function () {
    $user = User::factory()->create(['name' => 'Old Name']);

    $this->actingAs($user)
        ->postJson('/api/user/profile', ['name' => 'New Name'])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Profile updated successfully.')
        ->assertJsonPath('data.user.name', 'New Name');

    expect($user->fresh()->name)->toBe('New Name');
});

it('authenticated user can update their mobile number', function () {
    $user = User::factory()->create(['mobile_number' => '09000000000']);

    $this->actingAs($user)
        ->postJson('/api/user/profile', ['mobile_number' => '09999999999'])
        ->assertStatus(200)
        ->assertJsonPath('data.user.name', $user->name);

    expect($user->fresh()->mobile_number)->toBe('09999999999');
});

it('authenticated user can update their barangay', function () {
    $barangay1 = Barangay::factory()->create();
    $barangay2 = Barangay::factory()->create();
    $user = User::factory()->create(['barangay_id' => $barangay1->id]);

    $this->actingAs($user)
        ->postJson('/api/user/profile', ['barangay_id' => $barangay2->id])
        ->assertStatus(200);

    expect($user->fresh()->barangay_id)->toBe($barangay2->id);
});

it('profile update with no fields is a no-op and returns 200', function () {
    $user = User::factory()->create(['name' => 'Unchanged']);

    $this->actingAs($user)
        ->postJson('/api/user/profile', [])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    expect($user->fresh()->name)->toBe('Unchanged');
});

it('returns 422 when barangay_id does not exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/user/profile', ['barangay_id' => 99999])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['barangay_id']);
});

it('profile photo can be uploaded and stored', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/user/profile', [
            'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
        ])
        ->assertStatus(200);

    expect($user->fresh()->profile_photo_path)->not->toBeNull();
});

it('returns 401 when unauthenticated', function () {
    $this->postJson('/api/user/profile', ['name' => 'Test'])->assertStatus(401);
});

// ══════════════════════════════════════════════════════════════════════════════
// CHANGE PASSWORD
// ══════════════════════════════════════════════════════════════════════════════

it('user can change their password with correct current password', function () {
    $user = User::factory()->create(['password' => bcrypt('oldpassword')]);

    $this->actingAs($user)
        ->postJson('/api/user/password', [
            'current_password'      => 'oldpassword',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Password changed successfully.');
});

it('returns 422 when current password is wrong', function () {
    $user = User::factory()->create(['password' => bcrypt('correctpassword')]);

    $this->actingAs($user)
        ->postJson('/api/user/password', [
            'current_password'      => 'wrongpassword',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Current password is incorrect.');
});

it('returns 422 when password confirmation does not match', function () {
    $user = User::factory()->create(['password' => bcrypt('oldpassword')]);

    $this->actingAs($user)
        ->postJson('/api/user/password', [
            'current_password'      => 'oldpassword',
            'password'              => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('returns 422 when new password is shorter than 8 characters', function () {
    $user = User::factory()->create(['password' => bcrypt('oldpassword')]);

    $this->actingAs($user)
        ->postJson('/api/user/password', [
            'current_password'      => 'oldpassword',
            'password'              => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('google-only account can set a password without providing current_password', function () {
    $user = User::factory()->create([
        'is_google_account' => true,
        'password'          => null,
    ]);

    $this->actingAs($user)
        ->postJson('/api/user/password', [
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});

it('returns 401 when unauthenticated on change password', function () {
    $this->postJson('/api/user/password', [])->assertStatus(401);
});

// ══════════════════════════════════════════════════════════════════════════════
// SESSIONS
// ══════════════════════════════════════════════════════════════════════════════

it('user can list their active sessions', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('mobile');

    $this->withToken($token->plainTextToken)
        ->getJson('/api/user/sessions')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [['id', 'device', 'last_used_at', 'is_current']],
        ]);
});

it('current session is flagged as is_current = true', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('mobile');

    $response = $this->withToken($token->plainTextToken)
        ->getJson('/api/user/sessions')
        ->assertStatus(200);

    $sessions    = $response->json('data');
    $currentSess = collect($sessions)->firstWhere('is_current', true);
    expect($currentSess)->not->toBeNull();
});

it('user can revoke another session', function () {
    $user    = User::factory()->create();
    $session1 = $user->createToken('device-1');
    $session2 = $user->createToken('device-2');

    // Use session2 as the current token, revoke session1
    $this->withToken($session2->plainTextToken)
        ->deleteJson("/api/user/sessions/{$session1->accessToken->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Session revoked.');

    expect($user->tokens()->where('id', $session1->accessToken->id)->exists())->toBeFalse();
});

it('returns 403 when user tries to revoke their own current session', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('mobile');

    $this->withToken($token->plainTextToken)
        ->deleteJson("/api/user/sessions/{$token->accessToken->id}")
        ->assertStatus(403)
        ->assertJsonPath('success', false);
});

it('returns 404 when revoking a session that does not exist', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('mobile');

    $this->withToken($token->plainTextToken)
        ->deleteJson('/api/user/sessions/99999')
        ->assertStatus(404)
        ->assertJsonPath('success', false);
});

it('returns 401 when unauthenticated on sessions endpoints', function () {
    $this->getJson('/api/user/sessions')->assertStatus(401);
    $this->deleteJson('/api/user/sessions/1')->assertStatus(401);
});
