<?php

namespace App\Http\Controllers\Message;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\SendMessageRequest;
use App\Models\Booking;
use App\Models\Message;
use App\Services\Message\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MessageController extends Controller
{
    public function __construct(private readonly MessageService $service) {}

    // ── Spec URLs (/api/messages/...) ──────────────────────────────────────────

    /**
     * GET /api/messages
     * Chat inbox — one row per booking, most recent first.
     */
    public function inbox(Request $request): JsonResponse
    {
        $conversations = $this->service->inbox($request->user());

        return response()->json([
            'success' => true,
            'data'    => ['conversations' => $conversations],
        ]);
    }

    /**
     * GET /api/messages/{bookingId}
     * Spec-URL message thread. Also marks unread messages as read.
     */
    public function thread(Request $request, int $bookingId): JsonResponse
    {
        $booking = Booking::find($bookingId);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($request->user()->cannot('messages', $booking)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $paginator = $this->service->thread($booking, $request->user());

        return response()->json([
            'success' => true,
            'data'    => [
                'booking'    => [
                    'id'           => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'status'       => $booking->status,
                ],
                'messages'   => $this->formatMessages($paginator->getCollection()),
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
     * POST /api/messages/{bookingId}
     * Spec-URL send message with optional attachment.
     */
    public function sendViaSpecUrl(SendMessageRequest $request, int $bookingId): JsonResponse
    {
        return $this->sendMessage($request, $bookingId);
    }

    // ── Legacy URLs (/api/bookings/{id}/messages) ─────────────────────────────

    /**
     * GET /api/bookings/{id}/messages
     */
    public function index(Request $request, int $id): JsonResponse
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($request->user()->cannot('messages', $booking)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $paginator = $this->service->thread($booking, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Messages retrieved.',
            'data'    => [
                'messages'   => $this->formatMessages($paginator->getCollection()),
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
     * POST /api/bookings/{id}/messages
     */
    public function store(SendMessageRequest $request, int $id): JsonResponse
    {
        return $this->sendMessage($request, $id);
    }

    // ── Shared ────────────────────────────────────────────────────────────────

    private function sendMessage(SendMessageRequest $request, int $bookingId): JsonResponse
    {
        $booking = Booking::find($bookingId);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($request->user()->cannot('messages', $booking)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        try {
            $msg = $this->service->store(
                $booking,
                $request->user(),
                $request->input('message'),
                $request->hasFile('attachment') ? $request->file('attachment') : null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Message sent.',
            'data'    => [
                'message' => [
                    'id'             => $msg->id,
                    'sender_id'      => $msg->sender_id,
                    'sender_name'    => $msg->sender->name,
                    'content'        => $msg->content ?: null,
                    'attachment_url' => $msg->attachment_path
                        ? asset('storage/' . $msg->attachment_path)
                        : null,
                    'is_read'        => $msg->is_read,
                    'created_at'     => $msg->created_at->toISOString(),
                ],
            ],
        ], 201);
    }

    private function formatMessages(\Illuminate\Support\Collection $messages): array
    {
        return $messages->map(fn (Message $m) => [
            'id'             => $m->id,
            'sender_id'      => $m->sender_id,
            'sender_name'    => $m->sender->name,
            'content'        => $m->content ?: null,
            'attachment_url' => $m->attachment_path
                ? asset('storage/' . $m->attachment_path)
                : null,
            'is_read'        => $m->is_read,
            'created_at'     => $m->created_at->toISOString(),
        ])->all();
    }
}
