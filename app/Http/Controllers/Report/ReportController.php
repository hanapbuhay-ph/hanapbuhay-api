<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\CreateReportRequest;
use App\Models\Booking;
use App\Models\Report;
use App\Services\Report\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $service) {}

    public function store(CreateReportRequest $request): JsonResponse
    {
        $booking = Booking::findOrFail($request->integer('booking_id'));

        $this->authorize('store', [Report::class, $booking]);

        try {
            $report = $this->service->create(
                $request->validated(),
                $request->file('evidence_photos', []),
                $request->user()
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Report submitted successfully.',
            'data'    => [
                'report' => [
                    'id'          => $report->id,
                    'booking_id'  => $report->booking_id,
                    'reason'      => $report->reason,
                    'description' => $report->description,
                    'status'      => $report->status,
                ],
            ],
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->listForUser($request->user());

        $reports = $paginator->getCollection()->map(fn (Report $r) => [
            'id'            => $r->id,
            'booking_id'    => $r->booking_id,
            'booking_code'  => $r->booking->booking_code,
            'reported_user' => $r->reportedUser->name,
            'reason'        => $r->reason,
            'status'        => $r->status,
            'created_at'    => $r->created_at->toISOString(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reports retrieved.',
            'data'    => [
                'reports'    => $reports,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'last_page'    => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $report = $this->service->find($id);

        $this->authorize('view', $report);

        return response()->json([
            'success' => true,
            'message' => 'Report retrieved.',
            'data'    => [
                'report' => [
                    'id'             => $report->id,
                    'booking_id'     => $report->booking_id,
                    'booking_code'   => $report->booking->booking_code,
                    'reported_user'  => $report->reportedUser->name,
                    'reason'         => $report->reason,
                    'description'    => $report->description,
                    'evidence_paths' => $report->evidence_paths,
                    'status'         => $report->status,
                    'admin_remarks'  => $report->admin_remarks,
                    'created_at'     => $report->created_at->toISOString(),
                ],
            ],
        ]);
    }
}
