<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Message;
use App\Models\User;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminChatLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('messages')
            ->join('bookings', 'messages.booking_id', '=', 'bookings.id')
            ->join('users as clients', 'bookings.client_id', '=', 'clients.id')
            ->join('users as workers', 'bookings.worker_id', '=', 'workers.id')
            ->whereNull('messages.deleted_at')
            ->whereNull('bookings.deleted_at')
            ->select([
                'bookings.client_id',
                'bookings.worker_id',
                'clients.name as client_name',
                'clients.profile_photo_path as client_profile_photo_path',
                'workers.name as worker_name',
                'workers.profile_photo_path as worker_profile_photo_path',
            ])
            ->selectRaw('MAX(messages.created_at) as last_message_at')
            ->selectRaw('COUNT(messages.id) as messages_count')
            ->selectRaw('COUNT(DISTINCT bookings.id) as bookings_count')
            ->selectRaw('SUM(CASE WHEN messages.is_read THEN 0 ELSE 1 END) as unread_count')
            ->groupBy(
                'bookings.client_id',
                'bookings.worker_id',
                'clients.name',
                'clients.profile_photo_path',
                'workers.name',
                'workers.profile_photo_path',
            );

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery->where('clients.name', 'like', "%{$search}%")
                    ->orWhere('clients.email', 'like', "%{$search}%")
                    ->orWhere('workers.name', 'like', "%{$search}%")
                    ->orWhere('workers.email', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderByDesc('last_message_at')->paginate(15);
        $conversations = collect($paginator->items())->map(function (object $pair): array {
            $lastMessage = Message::query()
                ->select(['id', 'booking_id', 'attachment_path', 'created_at'])
                ->whereHas('booking', fn ($bookingQuery) => $bookingQuery
                    ->where('client_id', $pair->client_id)
                    ->where('worker_id', $pair->worker_id))
                ->latest('created_at')
                ->latest('id')
                ->first();

            return [
                'conversation_id' => "{$pair->client_id}-{$pair->worker_id}",
                'client' => [
                    'id' => (int) $pair->client_id,
                    'name' => $pair->client_name,
                    'profile_photo_url' => self::profilePhotoUrl($pair->client_profile_photo_path),
                ],
                'worker' => [
                    'id' => (int) $pair->worker_id,
                    'name' => $pair->worker_name,
                    'profile_photo_url' => self::profilePhotoUrl($pair->worker_profile_photo_path),
                ],
                'last_message_has_attachment' => (bool) $lastMessage?->attachment_path,
                'last_message_at' => $lastMessage?->created_at?->toIso8601String(),
                'messages_count' => (int) $pair->messages_count,
                'bookings_count' => (int) $pair->bookings_count,
                'unread_count' => (int) $pair->unread_count,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'conversations' => $conversations,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function showConversation(int $clientId, int $workerId): JsonResponse
    {
        $client = User::query()->where('role', 'client')->find($clientId);
        $worker = User::query()->where('role', 'worker')->find($workerId);
        $bookings = Booking::query()
            ->where('client_id', $clientId)
            ->where('worker_id', $workerId)
            ->whereHas('messages')
            ->get(['id', 'booking_code']);

        if ($client === null || $worker === null || $bookings->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Conversation not found.'], 404);
        }

        $paginator = Message::query()
            ->select(['id', 'booking_id', 'sender_id', 'attachment_path', 'is_read', 'created_at'])
            ->with('booking:id,booking_code')
            ->whereIn('booking_id', $bookings->pluck('id'))
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(30);

        $messages = $paginator->getCollection()->map(fn (Message $message): array => [
            'id' => $message->id,
            'booking_code' => $message->booking?->booking_code,
            'sender_role' => $message->sender_id === $clientId ? 'client' : 'worker',
            'has_attachment' => (bool) $message->attachment_path,
            'is_read' => $message->is_read,
            'created_at' => $message->created_at?->toIso8601String(),
        ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => [
                    'client' => [
                        'id' => $client->id,
                        'name' => $client->name,
                        'profile_photo_url' => self::profilePhotoUrl($client->profile_photo_path),
                    ],
                    'worker' => [
                        'id' => $worker->id,
                        'name' => $worker->name,
                        'profile_photo_url' => self::profilePhotoUrl($worker->profile_photo_path),
                    ],
                    'bookings_count' => $bookings->count(),
                ],
                'messages' => $messages,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function show(int|string $bookingId): JsonResponse
    {
        $booking = Booking::with(['client:id,name', 'worker:id,name'])->find($bookingId);

        if ($booking === null) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        $paginator = Message::query()
            ->select(['id', 'booking_id', 'sender_id', 'attachment_path', 'is_read', 'created_at'])
            ->where('booking_id', $booking->id)
            ->orderBy('created_at')
            ->paginate(30);

        $messages = $paginator->getCollection()->map(fn (Message $message): array => [
            'id' => $message->id,
            'sender_role' => $message->sender_id === $booking->client_id ? 'client' : 'worker',
            'has_attachment' => (bool) $message->attachment_path,
            'is_read' => $message->is_read,
            'created_at' => $message->created_at?->toISOString(),
        ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'booking' => [
                    'id' => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'status' => $booking->status,
                ],
                'messages' => $messages,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    private static function profilePhotoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return app(MediaStorageService::class)->publicUrl($path);
    }
}
