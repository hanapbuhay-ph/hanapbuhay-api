<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Http\Requests\Worker\UpdateWorkerProfileRequest;
use App\Services\Worker\WorkerService;
use Illuminate\Http\JsonResponse;

class WorkerProfileController extends Controller
{
    public function __construct(private readonly WorkerService $workerService)
    {
    }

    public function update(UpdateWorkerProfileRequest $request): JsonResponse
    {
        $data = $this->workerService->updateProfile($request->user(), $request);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated.',
            'data'    => ['worker_profile' => $data],
        ]);
    }
}
