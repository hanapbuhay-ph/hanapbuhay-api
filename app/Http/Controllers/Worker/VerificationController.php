<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Http\Requests\Worker\SubmitVerificationRequest;
use App\Services\Worker\WorkerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function __construct(private readonly WorkerService $workerService)
    {
    }

    public function submit(SubmitVerificationRequest $request): JsonResponse
    {
        $data = $this->workerService->submitVerification($request->user(), $request);

        return response()->json([
            'success' => true,
            'message' => 'Documents submitted for review.',
            'data'    => $data,
        ], 201);
    }

    public function status(Request $request): JsonResponse
    {
        $data = $this->workerService->getVerificationStatus($request->user());

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
