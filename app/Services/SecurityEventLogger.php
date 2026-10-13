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
    }
}
