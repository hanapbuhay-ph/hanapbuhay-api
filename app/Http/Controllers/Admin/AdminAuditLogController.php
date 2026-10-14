<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exceptions\BusinessRuleException;
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
        $request->validate([
            'category' => ['nullable', 'in:admin_activity,security'],
            'outcome' => ['nullable', 'in:success,failure'],
            'ip_address' => ['nullable', 'string', 'max:45'],
        ]);

        $paginator = $this->adminService->listAuditLogs(
            $request->filled('admin_id')    ? $request->integer('admin_id')                       : null,
            $request->filled('action')      ? $request->string('action')->toString()               : null,
            $request->filled('target_type') ? $request->string('target_type')->toString()          : null,
            $request->query('date_from'),
            $request->query('date_to'),
            $request->query('admin_name'),
            $request->query('category'),
            $request->query('outcome'),
            $request->query('ip_address'),
        );

        $logs = collect($paginator->items())->map(fn ($log) => [
            'id'          => $log->id,
            'category'    => $log->category,
            'outcome'     => $log->outcome,
            'action'      => $log->action,
            'target_type' => $log->target_type,
            'target_id'   => $log->target_id,
            'details'     => $log->details,
            'ip_address'  => $log->ip_address,
            'user_agent'  => $log->user_agent,
            'admin'       => [
                'id' => $log->admin?->id,
                'name' => $log->actor_name ?? $log->admin?->name ?? 'Unknown',
                'email' => $log->actor_email ?? $log->admin?->email,
            ],
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

    /**
     * DELETE /api/admin/audit-logs/{id}
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        try {
            $this->adminService->deleteAuditLog($id, $request->user());
        } catch (BusinessRuleException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        return response()->json([
            'success' => true,
            'message' => 'Audit log deleted.',
        ]);
    }

    /**
     * DELETE /api/admin/audit-logs
     */
    public function destroyAll(Request $request): JsonResponse
    {
        try {
            $deletedCount = $this->adminService->deleteAllAuditLogs($request->user());
        } catch (BusinessRuleException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        return response()->json([
            'success' => true,
            'message' => 'All audit and security logs deleted.',
            'data' => ['deleted_count' => $deletedCount],
        ]);
    }
}
