<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\User;

class SecurityEventLogger
{
    public function record(
        string $action,
        string $outcome,
        ?User $actor,
        ?int $targetId,
        array $details = [],
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        AdminAuditLog::create([
            'admin_id' => $actor?->id,
            'actor_name' => $actor?->name ?? ($details['email'] ?? 'Unknown'),
            'actor_email' => $actor?->email ?? ($details['email'] ?? null),
            'category' => 'security',
            'outcome' => $outcome,
            'action' => $action,
            'target_type' => $targetId === null ? null : 'User',
            'target_id' => $targetId,
            'details' => $details ?: null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 1024),
        ]);

        if ($outcome !== 'failure') {
            return;
        }

        if (str_starts_with($action, 'protected_account_')) {
            app(AdminNotificationService::class)->notifySecurityAlert(
                'Protected account access blocked',
                'An admin was denied an attempt to change or delete the Super Admin account.',
                '/audit-logs',
            );

            return;
        }

        if ($action === 'login' && $ipAddress !== null) {
            $recentFailures = AdminAuditLog::query()
                ->where('category', 'security')
                ->where('action', 'login')
                ->where('outcome', 'failure')
                ->where('ip_address', $ipAddress)
                ->where('created_at', '>=', now()->subMinutes(15))
                ->count();

            if ($recentFailures === 5) {
                app(AdminNotificationService::class)->notifySecurityAlert(
                    'Repeated sign-in failures',
                    'Five or more sign-in attempts failed from the same IP address within 15 minutes.',
                    '/audit-logs',
                );
            }
        }
    }
}
