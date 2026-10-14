<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Services\Admin\AdminService;
use App\Services\AdminNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function __construct(
        private readonly AdminNotificationService $notifications,
        private readonly AdminService $adminService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->notifications->pageForAdmin(
                $request->user(),
                (int) ($validated['per_page'] ?? 20),
                $validated['cursor'] ?? null,
            ),
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        if (! $this->notifications->markRead($request->user(), $id)) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        return response()->json(['success' => true, 'message' => 'Notification marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $this->notifications->markAllRead($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Notifications marked as read.',
            'data' => ['marked_count' => $count],
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return $this->forbidden();
        }

        $notification = AdminNotification::find($id);
        if ($notification === null || ! $this->notifications->deleteOne($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        $this->adminService->audit(
            $request->user(),
            'admin_notification_deleted',
            'AdminNotification',
            $id,
            ['type' => $notification->type, 'title' => $notification->title],
        );

        return response()->json(['success' => true, 'message' => 'Notification deleted.']);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return $this->forbidden();
        }

        $count = $this->notifications->deleteAll();
        if ($count > 0) {
            $this->adminService->audit(
                $request->user(),
                'admin_notifications_cleared',
                'AdminNotificationInbox',
                null,
                ['deleted_count' => $count, 'scope' => 'all_admins'],
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'All admin notifications deleted.',
            'data' => ['deleted_count' => $count],
        ]);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Only the super admin can delete admin notifications.',
        ], 403);
    }
}
