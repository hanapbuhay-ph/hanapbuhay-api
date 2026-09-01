<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResolveReportRequest;
use App\Services\Admin\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function __construct(
        private readonly AdminService $adminService,
    ) {}

    /**
     * GET /api/admin/reports
     * List all reports, optionally filtered by status.
     */
    public function index(Request $request): JsonResponse
    {
        $paginated = $this->adminService->listReports(
            $request->query('status'),
            $request->query('reason'),
        );

        $reports = collect($paginated->items())->map(function ($report): array {
            return [
                'id'            => $report->id,
                'reason'        => $report->reason,
                'status'        => $report->status,
                'created_at'    => $report->created_at?->toIso8601String(),
                'booking'       => [
                    'id'           => $report->booking?->id,
                    'booking_code' => $report->booking?->booking_code,
                ],
                'reporter'      => [
                    'id'   => $report->reporter?->id,
                    'name' => $report->reporter?->name,
                ],
                'reported_user' => [
                    'id'   => $report->reportedUser?->id,
                    'name' => $report->reportedUser?->name,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Reports retrieved.',
            'data'    => [
                'reports'    => $reports,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ],
        ], 200);
    }

    /**
     * GET /api/admin/reports/{id}
     * View a single report's full detail, including evidence_paths and admin_notes.
     */
    public function show(int $id): JsonResponse
    {
        $report = $this->adminService->getReport($id);

        return response()->json([
            'success' => true,
            'message' => 'Report retrieved.',
            'data'    => [
                'id'             => $report->id,
                'reason'         => $report->reason,
                'description'    => $report->description,
                'evidence_paths' => $report->evidence_paths,
                'status'         => $report->status,
                'admin_notes'    => $report->admin_remarks,
                'created_at'     => $report->created_at?->toIso8601String(),
                'updated_at'     => $report->updated_at?->toIso8601String(),
                'booking'        => [
                    'id'           => $report->booking?->id,
                    'booking_code' => $report->booking?->booking_code,
                ],
                'reporter'       => [
                    'id'   => $report->reporter?->id,
                    'name' => $report->reporter?->name,
                ],
                'reported_user'  => [
                    'id'   => $report->reportedUser?->id,
                    'name' => $report->reportedUser?->name,
                ],
            ],
        ], 200);
    }

    /**
     * PATCH /api/admin/reports/{id}/resolve
     * Update report status to resolved or dismissed.
     */
    public function resolve(ResolveReportRequest $request, int $id): JsonResponse
    {
        $report = $this->adminService->resolveReport(
            $id,
            $request->validated('status'),
            $request->validated('admin_notes'),
            $request->validated('action'),
            $request->validated('resolution_action'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Report updated.',
            'data'    => [
                'report_id'   => $report->id,
                'status'      => $report->status,
                'admin_notes' => $report->admin_remarks,
            ],
        ], 200);
    }
}
