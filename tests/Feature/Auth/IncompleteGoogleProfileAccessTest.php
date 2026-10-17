<?php

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('restricts incomplete Google profiles to profile completion and account lookup', function () {
    $user = User::factory()->create([
        'role' => null,
        'is_google_account' => true,
    ]);

    $this->actingAs($user)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.role', null);

    $this->actingAs($user)
        ->getJson('/api/workers')
        ->assertForbidden()
        ->assertJsonPath('code', 'profile_incomplete');

    $barangay = Barangay::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/auth/google/complete-profile', [
            'name' => 'Google User',
            'mobile_number' => '09171234567',
            'role' => 'client',
            'barangay_id' => $barangay->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.user.role', 'client');

    $this->actingAs($user->fresh())
        ->getJson('/api/workers')
        ->assertOk();
});

it('allows admins to filter accounts with incomplete profiles', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $incompleteUser = User::factory()->create([
        'role' => null,
        'is_google_account' => true,
    ]);
    User::factory()->create(['role' => 'client']);

    $this->actingAs($admin)
        ->getJson('/api/admin/users?role=incomplete')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.users.0.id', $incompleteUser->id)
        ->assertJsonPath('data.users.0.role', null);
});
