<?php

namespace App\Http\Controllers\Internal;

use App\Console\Commands\ProcessScheduledUserAccountActions;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ProcessScheduledUserAccountActionsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $expectedToken = (string) config('services.account_actions.scheduler_token');
        $providedToken = (string) $request->bearerToken();

        if ($expectedToken === '') {
            return response()->json([
                'success' => false,
                'message' => 'Scheduled account-action processing is not configured.',
            ], 503);
        }

        if (! hash_equals($expectedToken, $providedToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $exitCode = Artisan::call(ProcessScheduledUserAccountActions::class);
        if ($exitCode !== ProcessScheduledUserAccountActions::SUCCESS) {
            return response()->json([
                'success' => false,
                'message' => 'One or more scheduled account actions could not be processed. Check application logs.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Due account actions processed.',
        ]);
    }
}
