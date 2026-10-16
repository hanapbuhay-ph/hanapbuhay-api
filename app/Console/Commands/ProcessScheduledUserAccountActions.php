<?php

namespace App\Console\Commands;

use App\Models\UserAccountAction;
use App\Services\Admin\AdminService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledUserAccountActions extends Command
{
    protected $signature = 'users:process-scheduled-account-actions';

    protected $description = 'Apply due scheduled user suspensions and deletions.';

    public function handle(AdminService $adminService): int
    {
        $hadFailures = false;

        UserAccountAction::query()
            ->where('status', 'pending')
            ->where('scheduled_for', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($actions) use ($adminService, &$hadFailures): void {
                foreach ($actions as $action) {
                    try {
                        $adminService->processScheduledAccountAction($action);
                    } catch (\Throwable $exception) {
                        $action->update([
                            'failure_reason' => mb_substr($exception->getMessage(), 0, 2000),
                        ]);
                        $hadFailures = true;
                        Log::error('Failed to process scheduled user account action.', [
                            'action_id' => $action->id,
                            'target_user_id' => $action->target_user_id,
                            'action' => $action->action,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        return $hadFailures ? self::FAILURE : self::SUCCESS;
    }
}
