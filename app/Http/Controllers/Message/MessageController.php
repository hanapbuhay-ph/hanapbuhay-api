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

    public function index(Request $request, int $id): JsonResponse
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($request->user()->cannot('messages', $booking)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $paginator = $this->service->index($booking);

        $messages = $paginator->getCollection()->map(fn (Message $m) => [
            'id'         => $m->id,
            'sender'     => ['id' => $m->sender->id, 'name' => $m->sender->name],
            'message'    => $m->content,
            'is_read'    => $m->is_read,
            'created_at' => $m->created_at->toISOString(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Messages retrieved.',
            'data'    => [
                'messages'   => $messages,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'last_page'    => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function store(SendMessageRequest $request, int $id): JsonResponse
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($request->user()->cannot('messages', $booking)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        try {
            $msg = $this->service->store($booking, $request->user(), $request->input('message'));
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Message sent.',
            'data'    => [
                'message' => [
                    'id'         => $msg->id,
                    'sender'     => ['id' => $msg->sender->id, 'name' => $msg->sender->name],
                    'message'    => $msg->content,
                    'is_read'    => $msg->is_read,
                    'created_at' => $msg->created_at->toISOString(),
                ],
            ],
        ], 201);
    }
}
