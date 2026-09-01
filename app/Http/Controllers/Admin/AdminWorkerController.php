<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTrustTierRequest;
use App\Services\Admin\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminWorkerController extends Controller
{
    public function __construct(private readonly AdminService $adminService) {}

    /**
     * POST /api/admin/workers/{workerProfileId}/trust-tier
     */
    public function updateTrustTier(UpdateTrustTierRequest $request, int $workerProfileId): JsonResponse
    {
        $profile = $this->adminService->updateTrustTier(
            $request->user(),
            $workerProfileId,
            $request->validated('trust_tier'),
            $request->validated('remarks'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Worker trust tier updated.',
            'data'    => [
                'worker_profile_id' => $profile->id,
                'trust_tier'        => $profile->trust_tier,
                'remarks'           => $profile->verification_remarks,
            ],
        ]);
    }
}
