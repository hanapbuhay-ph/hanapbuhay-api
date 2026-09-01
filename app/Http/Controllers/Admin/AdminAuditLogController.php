<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    public function __construct(private readonly AdminService $adminService) {}

    /**
     * GET /api/admin/audit-logs
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->adminService->listAuditLogs(
            $request->filled('admin_id')    ? $request->integer('admin_id')                       : null,
            $request->filled('action')      ? $request->string('action')->toString()               : null,
            $request->filled('target_type') ? $request->string('target_type')->toString()          : null,
            $request->query('date_from'),
            $request->query('date_to'),
        );

        $logs = collect($paginator->items())->map(fn ($log) => [
            'id'          => $log->id,
            'action'      => $log->action,
            'target_type' => $log->target_type,
            'target_id'   => $log->target_id,
            'details'     => $log->details,
            'ip_address'  => $log->ip_address,
            'admin'       => ['id' => $log->admin?->id, 'name' => $log->admin?->name],
            'created_at'  => $log->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Audit logs retrieved.',
            'data'    => [
                'logs'       => $logs,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'last_page'    => $paginator->lastPage(),
                ],
            ],
        ]);
    }
}
