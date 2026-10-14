<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('locks mobile and admin password login for fifteen minutes after five failed attempts', function () {
    $user = User::factory()->create([
        'role' => 'client',
        'password' => 'correct-password',
    ]);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $endpoint = $attempt % 2 === 0
            ? '/api/auth/admin/login'
            : '/api/auth/login';

        $response = $this->postJson($endpoint, [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $response->assertStatus(429)
        ->assertJsonPath('success', false);
    expect($response->json('data.retry_after_seconds'))
        ->toBeGreaterThan(0)
        ->toBeLessThanOrEqual(900);
    $response->assertHeader(
        'Retry-After',
        (string) $response->json('data.retry_after_seconds'),
    );

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ])->assertStatus(429);
});

it('clears failed login attempts after a successful password login', function () {
    $user = User::factory()->create([
        'role' => 'client',
        'password' => 'correct-password',
    ]);

    for ($attempt = 0; $attempt < 4; $attempt++) {
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ])->assertOk();

    for ($attempt = 0; $attempt < 4; $attempt++) {
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }
});

it('blocks Google login during the password-login lockout for both portals', function (
    string $role,
    string $passwordLoginEndpoint,
    string $googleLoginEndpoint,
) {
    config(['services.google.client_id' => 'test-google-client']);
    Http::fake([
        'oauth2.googleapis.com/tokeninfo*' => Http::response([
            'sub' => 'google-locked-user',
            'email' => 'locked@example.com',
            'email_verified' => 'true',
            'aud' => 'test-google-client',
        ]),
    ]);

    $user = User::factory()->create([
        'email' => 'locked@example.com',
        'role' => $role,
        'password' => 'correct-password',
    ]);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson($passwordLoginEndpoint, [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $this->postJson($googleLoginEndpoint, [
        'google_token' => 'valid-google-token',
    ])
        ->assertStatus(429)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Too many failed login attempts. Try again when the countdown ends.')
        ->assertHeader('Retry-After');

    expect($user->tokens()->count())->toBe(0);
})->with([
    ['client', '/api/auth/login', '/api/auth/google'],
    ['admin', '/api/auth/admin/login', '/api/auth/admin/google'],
]);

it('starts a one-minute password cooldown after a valid change submission', function () {
    $user = User::factory()->create([
        'password' => 'correct-password',
    ]);

    $payload = [
        'current_password' => 'wrong-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ];

    $this->actingAs($user)
        ->postJson('/api/user/password', $payload)
        ->assertUnprocessable();

    $cooldownResponse = $this->actingAs($user)
        ->getJson('/api/user/password/cooldown')
        ->assertOk();
    expect($cooldownResponse->json('data.retry_after_seconds'))
        ->toBeGreaterThan(0)
        ->toBeLessThanOrEqual(60);

    $rateLimitedResponse = $this->actingAs($user)
        ->postJson('/api/user/password', [
            ...$payload,
            'current_password' => 'correct-password',
        ])
        ->assertStatus(429);
    expect($rateLimitedResponse->json('data.retry_after_seconds'))
        ->toBeGreaterThan(0)
        ->toBeLessThanOrEqual(60);
    $rateLimitedResponse->assertHeader(
        'Retry-After',
        (string) $rateLimitedResponse->json('data.retry_after_seconds'),
    );
});
