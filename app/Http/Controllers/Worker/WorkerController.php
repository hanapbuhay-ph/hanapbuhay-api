<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Services\Worker\WorkerSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkerController extends Controller
{
    public function __construct(private readonly WorkerSearchService $workerSearchService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $this->workerSearchService->getWorkers($request->user(), $request);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    public function show(Request $request, int $workerProfileId): JsonResponse
    {
        $data = $this->workerSearchService->getWorker($request->user(), $workerProfileId);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
