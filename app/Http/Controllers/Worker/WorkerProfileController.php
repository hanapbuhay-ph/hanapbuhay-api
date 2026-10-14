<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Http\Requests\Worker\UpdateWorkerProfileRequest;
use App\Services\Worker\WorkerSearchService;
use App\Services\Worker\WorkerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkerProfileController extends Controller
{
    public function __construct(
        private readonly WorkerService $workerService,
        private readonly WorkerSearchService $workerSearchService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $worker = $this->workerSearchService->getAuthenticatedWorker($request->user());

        return response()->json([
            'success' => true,
            'data' => ['worker' => $worker],
        ]);
    }

    public function update(UpdateWorkerProfileRequest $request): JsonResponse
    {
        $data = $this->workerService->updateProfile($request->user(), $request);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated.',
            'data' => ['worker_profile' => $data],
        ]);
    }
}
