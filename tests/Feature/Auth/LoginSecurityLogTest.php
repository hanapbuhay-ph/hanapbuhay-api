<?php

use App\Models\AdminAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records failed login attempts without storing credentials', function () {
    $response = $this->withHeader('User-Agent', 'SecurityLogTest/1.0')
        ->postJson('/api/auth/login', [
            'email' => 'unknown@example.com',
            'password' => 'never-store-this-password',
        ]);

    $response->assertUnauthorized();

    $log = AdminAuditLog::query()->where('action', 'login')->firstOrFail();

    expect($log->category)->toBe('security')
        ->and($log->outcome)->toBe('failure')
        ->and($log->admin_id)->toBeNull()
        ->and($log->actor_email)->toBe('unknown@example.com')
        ->and($log->ip_address)->not->toBeNull()
        ->and($log->user_agent)->toBe('SecurityLogTest/1.0')
        ->and($log->details)->toMatchArray([
            'email' => 'unknown@example.com',
            'reason' => 'invalid_credentials',
        ])
        ->and(json_encode($log->toArray()))->not->toContain('never-store-this-password');
});
