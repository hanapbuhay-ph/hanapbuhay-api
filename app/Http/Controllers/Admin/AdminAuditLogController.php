<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Services\Admin\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAuditLogController extends Controller
{
    public function __construct(private readonly AdminService $adminService) {}

    /**
     * GET /api/admin/audit-logs
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        $paginator = $this->adminService->listAuditLogs(
            $filters['admin_id'] ?? null,
            $filters['action'] ?? null,
            $filters['target_type'] ?? null,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
            $filters['admin_name'] ?? null,
            $filters['category'] ?? null,
            $filters['outcome'] ?? null,
            $filters['ip_address'] ?? null,
        );

        $logs = collect($paginator->items())->map(fn ($log) => [
            'id' => $log->id,
            'category' => $log->category,
            'outcome' => $log->outcome,
            'action' => $log->action,
            'target_type' => $log->target_type,
            'target_id' => $log->target_id,
            'details' => $log->details,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'admin' => [
                'id' => $log->admin?->id,
                'name' => $log->actor_name ?? $log->admin?->name ?? 'Unknown',
                'email' => $log->actor_email ?? $log->admin?->email,
            ],
            'created_at' => $log->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Audit logs retrieved.',
            'data' => [
                'logs' => $logs,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * GET /api/admin/audit-logs/export
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $suffix = ($filters['date_from'] ?? null) || ($filters['date_to'] ?? null)
            ? '-'.($filters['date_from'] ?? 'all').'-'.($filters['date_to'] ?? 'all')
            : '-all-time';

        return response()->streamDownload(function () use ($filters): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open the audit log export stream.');
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'Log ID',
                'Category',
                'Outcome',
                'Action',
                'Actor',
                'Actor Email',
                'Target Type',
                'Target ID',
                'Details',
                'IP Address',
                'User Agent',
                'Created At',
            ]);

            $logs = $this->adminService->auditLogsForExport(
                $filters['admin_id'] ?? null,
                $filters['action'] ?? null,
                $filters['target_type'] ?? null,
                $filters['date_from'] ?? null,
                $filters['date_to'] ?? null,
                $filters['admin_name'] ?? null,
                $filters['category'] ?? null,
                $filters['outcome'] ?? null,
                $filters['ip_address'] ?? null,
            );

            foreach ($logs as $log) {
                $values = [
                    $log->id,
                    $log->category,
                    $log->outcome,
                    $log->action,
                    $log->actor_name ?? $log->admin?->name ?? 'Unknown',
                    $log->actor_email ?? $log->admin?->email,
                    $log->target_type,
                    $log->target_id,
                    $log->details === null
                        ? null
                        : json_encode($log->details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    $log->ip_address,
                    $log->user_agent,
                    $log->created_at?->toIso8601String(),
                ];

                fputcsv($stream, array_map(
                    fn (mixed $value): string => $this->safeCsvValue($value),
                    $values,
                ));
            }

            fclose($stream);
        }, 'hanapbuhay-audit-logs'.$suffix.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
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

    private function validatedFilters(Request $request): array
    {
        $filters = $request->validate([
            'admin_id' => ['nullable', 'integer', 'min:1'],
            'action' => ['nullable', 'string', 'max:255'],
            'target_type' => ['nullable', 'string', 'max:255'],
            'admin_name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'in:admin_activity,security'],
            'outcome' => ['nullable', 'in:success,failure'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        foreach (['action', 'target_type', 'admin_name', 'ip_address'] as $field) {
            if (isset($filters[$field])) {
                $filters[$field] = trim($filters[$field]);
                if ($filters[$field] === '') {
                    unset($filters[$field]);
                }
            }
        }

        return $filters;
    }

    private function safeCsvValue(mixed $value): string
    {
        $value = $value === null ? '' : (string) $value;
        if (preg_match('/^[\s]*[=+\-@]/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
