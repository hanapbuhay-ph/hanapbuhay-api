<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminChatLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Booking::query()
            ->whereHas('messages')
            ->with(['client:id,name,profile_photo_path', 'worker:id,name,profile_photo_path'])
            ->withCount(['messages as unread_count' => fn ($messageQuery) => $messageQuery->where('is_read', false)])
            ->orderByDesc('updated_at');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery->where('booking_code', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('worker', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
            });
        }

        $paginator = $query->paginate(15);
        $bookingIds = collect($paginator->items())->pluck('id');
        $lastMessages = Message::whereIn('booking_id', $bookingIds)
            ->latest('created_at')
            ->get()
            ->unique('booking_id')
            ->keyBy('booking_id');

        $conversations = collect($paginator->items())->map(function (Booking $booking) use ($lastMessages): array {
            $lastMessage = $lastMessages->get($booking->id);

            return [
                'booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'booking_status' => $booking->status,
                'client' => ['id' => $booking->client?->id, 'name' => $booking->client?->name],
                'worker' => ['id' => $booking->worker?->id, 'name' => $booking->worker?->name],
                'other_party' => [
                    'id' => $booking->worker?->id,
                    'name' => $booking->worker?->name,
                    'profile_photo_url' => $booking->worker?->profile_photo_path
                        ? asset('storage/' . $booking->worker->profile_photo_path)
                        : null,
                ],
                'last_message' => $lastMessage?->content,
                'last_message_at' => $lastMessage?->created_at?->toIso8601String(),
                'unread_count' => $booking->unread_count,
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

    public function show(int|string $bookingId): JsonResponse
    {
        $booking = Booking::with(['client:id,name', 'worker:id,name'])->find($bookingId);

        if ($booking === null) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        $paginator = Message::with('sender:id,name')
            ->where('booking_id', $booking->id)
            ->orderBy('created_at')
            ->paginate(30);

        $messages = $paginator->getCollection()->map(fn (Message $message): array => [
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender?->name,
            'sender_role' => $message->sender_id === $booking->client_id ? 'client' : 'worker',
            'content' => $message->content,
            'attachment_url' => $message->attachment_path ? asset('storage/' . $message->attachment_path) : null,
            'is_read' => $message->is_read,
            'is_flagged' => false,
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
}
