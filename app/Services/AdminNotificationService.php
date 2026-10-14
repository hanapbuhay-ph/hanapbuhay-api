<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\AdminNotificationRead;
use App\Models\User;

class AdminNotificationService
{
    public function create(string $type, string $title, string $body, array $data = []): AdminNotification
    {
        return AdminNotification::create([
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }

    public function pageForAdmin(User $admin, int $perPage = 20, ?string $cursor = null): array
    {
        $query = AdminNotification::query()
            ->with(['reads' => fn ($reads) => $reads->where('admin_id', $admin->id)])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $page = $query->cursorPaginate($perPage, ['*'], 'cursor', $cursor);

        return [
            'notifications' => collect($page->items())->map(fn (AdminNotification $notification) => [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'body' => $notification->body,
                'data' => $notification->data,
                'is_read' => $notification->reads->isNotEmpty(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ])->values(),
            'unread_count' => AdminNotification::query()
                ->whereDoesntHave('reads', fn ($reads) => $reads->where('admin_id', $admin->id))
                ->count(),
            'pagination' => [
                'next_cursor' => $page->nextCursor()?->encode(),
                'has_more' => $page->hasMorePages(),
            ],
        ];
    }

    public function markRead(User $admin, int $notificationId): bool
    {
        if (! AdminNotification::whereKey($notificationId)->exists()) {
            return false;
        }

        AdminNotificationRead::firstOrCreate(
            [
                'admin_notification_id' => $notificationId,
                'admin_id' => $admin->id,
            ],
            ['read_at' => now()],
        );

        return true;
    }

    public function markAllRead(User $admin): int
    {
        $now = now();
        $markedCount = 0;

        AdminNotification::query()
            ->whereDoesntHave('reads', fn ($reads) => $reads->where('admin_id', $admin->id))
            ->select('id')
            ->chunkById(500, function ($notifications) use ($admin, $now, &$markedCount): void {
                $rows = $notifications->map(fn (AdminNotification $notification) => [
                    'admin_notification_id' => $notification->id,
                    'admin_id' => $admin->id,
                    'read_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                AdminNotificationRead::insertOrIgnore($rows);
                $markedCount += count($rows);
            });

        return $markedCount;
    }

    public function deleteOne(int $notificationId): bool
    {
        return (bool) AdminNotification::whereKey($notificationId)->delete();
    }

    public function deleteAll(): int
    {
        return AdminNotification::query()->delete();
    }

    public function notifySecurityAlert(string $title, string $body, string $url): void
    {
        $this->create('security_alert', $title, $body, ['url' => $url]);
    }
}
