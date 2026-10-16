<?php

namespace App\Services\Admin;

use App\Exceptions\BusinessRuleException;
use App\Mail\NotificationEmail;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Models\UserAccountAction;
use App\Services\Notification\NotificationService;
use App\Services\SecurityEventLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AdminUserActionService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly SecurityEventLogger $securityEventLogger,
    ) {}

    public function scheduleDeletion(User $admin, int|string $userId, string $reason): UserAccountAction
    {
        if ((int) $userId === (int) $admin->id) {
            throw new BusinessRuleException('You cannot delete your own account.');
        }

        return $this->schedule($admin, $userId, 'delete', $reason);
    }

    public function scheduleSuspension(User $admin, int|string $userId, string $reason): UserAccountAction
    {
        return $this->schedule($admin, $userId, 'suspend', $reason);
    }

    public function cancelPendingSuspension(int|string $userId): void
    {
        UserAccountAction::query()
            ->where('target_user_id', $userId)
            ->where('action', 'suspend')
            ->where('status', 'pending')
            ->update([
                'status' => 'cancelled',
                'completed_at' => now(),
            ]);
    }

    private function schedule(User $admin, int|string $userId, string $action, string $reason): UserAccountAction
    {
        if ((int) $userId === (int) $admin->id) {
            throw new BusinessRuleException('You cannot modify your own account.');
        }

        if (trim($reason) === '') {
            throw new BusinessRuleException('A reason is required.');
        }

        $target = User::query()->findOrFail($userId);
        if ($target->isSuperAdmin() && ! $admin->isSuperAdmin()) {
            $this->securityEventLogger->record(
                $action === 'delete'
                    ? 'protected_account_change_denied'
                    : 'protected_account_status_change_denied',
                'failure',
                $admin,
                $target->id,
                ['action' => $action],
                request()->ip(),
                request()->userAgent(),
            );

            throw new BusinessRuleException(
                'The super admin account can only be managed by the super admin.',
                403,
            );
        }

        $accountAction = DB::transaction(function () use ($admin, $userId, $action, $reason): UserAccountAction {
            $user = User::query()->lockForUpdate()->findOrFail($userId);

            if ($user->isSuperAdmin() && ! $admin->isSuperAdmin()) {
                throw new BusinessRuleException(
                    'The super admin account can only be managed by the super admin.',
                    403,
                );
            }

            if ($action === 'delete' && ! $admin->isSuperAdmin()) {
                throw new BusinessRuleException('Only the super admin can delete users.', 403);
            }

            if ($action === 'suspend' && ! $user->is_active) {
                throw new BusinessRuleException('This account is already suspended.');
            }

            $pending = UserAccountAction::query()
                ->where('target_user_id', $user->id)
                ->where('status', 'pending')
                ->exists();

            if ($pending) {
                throw new BusinessRuleException('This user already has a pending account action.');
            }

            $scheduledFor = now()->addHours(24);
            $accountAction = UserAccountAction::create([
                'target_user_id' => $user->id,
                'target_name' => $user->name,
                'target_email' => $user->email,
                'requested_by' => $admin->id,
                'requester_name' => $admin->name,
                'requester_email' => $admin->email,
                'action' => $action,
                'reason' => trim($reason),
                'scheduled_for' => $scheduledFor,
            ]);

            AdminAuditLog::create([
                'admin_id' => $admin->id,
                'actor_name' => $admin->name,
                'actor_email' => $admin->email,
                'category' => 'admin_activity',
                'outcome' => 'success',
                'action' => $action === 'delete' ? 'user_deletion_scheduled' : 'user_suspension_scheduled',
                'target_type' => 'User',
                'target_id' => $user->id,
                'details' => [
                    'reason' => $accountAction->reason,
                    'scheduled_for' => $scheduledFor->toIso8601String(),
                    'account_action_id' => $accountAction->id,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 1024) ?: null,
            ]);

            $this->securityEventLogger->record(
                $action === 'delete' ? 'user_deletion_scheduled' : 'user_suspension_scheduled',
                'success',
                $admin,
                $user->id,
                [
                    'reason' => $accountAction->reason,
                    'scheduled_for' => $scheduledFor->toIso8601String(),
                    'account_action_id' => $accountAction->id,
                ],
                request()->ip(),
                request()->userAgent(),
            );

            return $accountAction;
        });

        $title = $accountAction->action === 'delete'
            ? 'Account deletion scheduled'
            : 'Account suspension scheduled';
        $actionLabel = $accountAction->action === 'delete' ? 'permanently deleted' : 'suspended';
        $deadline = $accountAction->scheduled_for->copy()->timezone('Asia/Manila')->format('M j, Y g:i A T');
        $body = "Your account is scheduled to be {$actionLabel} after a 24-hour notice period, on {$deadline}. Reason: {$accountAction->reason}";
        $user = User::query()->findOrFail($accountAction->target_user_id);

        $this->notificationService->notify(
            $user,
            $title,
            $body,
            'account_action_scheduled',
            [
                'action_id' => $accountAction->id,
                'action' => $accountAction->action,
                'scheduled_for' => $accountAction->scheduled_for->toIso8601String(),
            ],
        );

        Mail::to($accountAction->target_email)->queue(
            (new NotificationEmail($title, $body))->afterCommit(),
        );

        return $accountAction;
    }
}
