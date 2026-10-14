<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateAdminReportRequest;
use App\Http\Requests\Admin\ResolveReportRequest;
use App\Services\Admin\AdminService;
use App\Services\Report\ReportService;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AdminReportController extends Controller
{
    public function __construct(
        private readonly AdminService $adminService,
        private readonly ReportService $reportService,
    ) {}

    public function store(CreateAdminReportRequest $request): JsonResponse
    {
        try {
            $report = $this->reportService->createOnBehalfOfUser(
                $request->user(),
                $request->validated(),
                $request->file('evidence_photos', []),
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $report->load(['reporter:id,name', 'reportedUser:id,name']);
        $this->adminService->audit(
            $request->user(),
            'report_submitted_on_behalf',
            'Report',
            $report->id,
            [
                'booking_id' => $report->booking_id,
                'reporter_role' => $request->validated('reporter_role'),
                'reporter_id' => $report->reported_by,
                'reporter_name' => $report->reporter?->name,
                'reported_user_id' => $report->reported_user,
                'reported_user_name' => $report->reportedUser?->name,
                'reason' => $report->reason,
                'evidence_count' => count($report->evidence_paths ?? []),
            ],
        );

        return response()->json([
            'success' => true,
            'message' => 'Report submitted on behalf of the user.',
            'data' => ['report' => ['id' => $report->id]],
        ], 201);
    }

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
                'id' => $report->id,
                'reason' => $report->reason,
                'status' => $report->status,
                'created_at' => $report->created_at?->toIso8601String(),
                'booking' => [
                    'id' => $report->booking?->id,
                    'booking_code' => $report->booking?->booking_code,
                ],
                'reporter' => [
                    'id' => $report->reporter?->id,
                    'name' => $report->reporter?->name,
                ],
                'submitted_by_admin' => $report->submittedByAdmin ? [
                    'id' => $report->submittedByAdmin->id,
                    'name' => $report->submittedByAdmin->name,
                ] : null,
                'reported_user' => [
                    'id' => $report->reportedUser?->id,
                    'name' => $report->reportedUser?->name,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Reports retrieved.',
            'data' => [
                'reports' => $reports,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ],
        ], 200);
    }

    /**
     * GET /api/admin/reports/{id}
     * View a single report's full detail, including evidence URLs and resolution metadata.
     */
    public function show(int|string $id): JsonResponse
    {
        $report = $this->adminService->getReport($id);

        return response()->json([
            'success' => true,
            'message' => 'Report retrieved.',
            'data' => [
                'id' => $report->id,
                'reason' => $report->reason,
                'description' => $report->description,
                'evidence_urls' => collect($report->evidence_paths ?? [])
                    ->map(fn (string $path): ?string => app(MediaStorageService::class)->privateUrl($path))
                    ->values(),
                'status' => $report->status,
                'admin_notes' => $report->admin_remarks,
                'resolution_action' => $report->resolution_action,
                'resolved_by' => $report->resolvedBy?->name,
                'resolved_at' => $report->resolved_at?->toIso8601String(),
                'created_at' => $report->created_at?->toIso8601String(),
                'updated_at' => $report->updated_at?->toIso8601String(),
                'booking' => [
                    'id' => $report->booking?->id,
                    'booking_code' => $report->booking?->booking_code,
                ],
                'reporter' => [
                    'id' => $report->reporter?->id,
                    'name' => $report->reporter?->name,
                ],
                'submitted_by_admin' => $report->submittedByAdmin ? [
                    'id' => $report->submittedByAdmin->id,
                    'name' => $report->submittedByAdmin->name,
                ] : null,
                'reported_user' => [
                    'id' => $report->reportedUser?->id,
                    'name' => $report->reportedUser?->name,
                ],
            ],
        ], 200);
    }

    /**
     * PATCH /api/admin/reports/{id}/resolve
     * Update report status to resolved or dismissed.
     */
    public function resolve(ResolveReportRequest $request, int|string $id): JsonResponse
    {
        $report = $this->adminService->resolveReport(
            $id,
            $request->validated('status'),
            $request->validated('admin_notes'),
            $request->validated('action'),
            $request->validated('resolution_action'),
            $request->user()->id,
        );

        return response()->json([
            'success' => true,
            'message' => 'Report updated.',
            'data' => [
                'report_id' => $report->id,
                'status' => $report->status,
                'admin_notes' => $report->admin_remarks,
            ],
        ], 200);
    }
}
