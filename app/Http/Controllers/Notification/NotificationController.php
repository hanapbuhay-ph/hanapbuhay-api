<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\RegisterDeviceRequest;
use App\Models\DeviceToken;
use App\Models\HanapbuhayNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NotificationController extends Controller
{
    /**
     * POST /api/notifications/register-device
     * Store or refresh the user's FCM device token.
     */
    public function registerDevice(RegisterDeviceRequest $request): JsonResponse
    {
        DeviceToken::updateOrCreate(
            [
                'user_id'   => $request->user()->id,
                'fcm_token' => $request->input('fcm_token'),
            ],
            [
                'device_type' => $request->input('device_type'),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Device registered successfully.',
            'data'    => [],
        ]);
    }

    /**
     * GET /api/notifications
     * Paginated list of the authenticated user's in-app notifications,
     * most recent first. Returns unread count alongside.
     */
    public function index(Request $request): JsonResponse
    {
        $user      = $request->user();
        $paginator = HanapbuhayNotification::where('user_id', $user->id)
            ->latest()
            ->paginate(20);

        $notifications = collect($paginator->items())->map(fn (HanapbuhayNotification $n) => [
            'id'         => $n->id,
            'title'      => $n->title,
            'body'       => $n->body,
            'type'       => $n->type,
            'data'       => $n->data,
            'is_read'    => $n->is_read,
            'created_at' => $n->created_at?->toIso8601String(),
        ]);

        $unreadCount = HanapbuhayNotification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'notifications' => $notifications,
                'unread_count'  => $unreadCount,
                'pagination'    => [
                    'current_page' => $paginator->currentPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'last_page'    => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/notifications/{id}/read
     * Mark a single notification as read.
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = HanapbuhayNotification::where('user_id', $request->user()->id)
            ->find($id);

        if (! $notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        if (! $notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => Carbon::now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * POST /api/notifications/read-all
     * Mark every unread notification for the authenticated user as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        HanapbuhayNotification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => Carbon::now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }
}
