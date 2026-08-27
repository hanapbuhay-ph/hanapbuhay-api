<?php

namespace App\Services\Message;

use App\Events\NewMessage;
use App\Models\Booking;
use App\Models\Message;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use RuntimeException;

class MessageService
{
    public function index(Booking $booking): LengthAwarePaginator
    {
        return Message::with('sender:id,name')
            ->where('booking_id', $booking->id)
            ->orderBy('created_at')
            ->paginate(30);
    }

    /**
     * @throws RuntimeException
     */
    public function store(Booking $booking, User $sender, string $content): Message
    {
        if (in_array($booking->status, ['cancelled', 'declined'], true)) {
            throw new RuntimeException('You cannot send messages on an inactive booking.');
        }

        $receiverId = $sender->id === $booking->client_id
            ? $booking->worker_id
            : $booking->client_id;

        $message = Message::create([
            'booking_id'  => $booking->id,
            'sender_id'   => $sender->id,
            'receiver_id' => $receiverId,
            'content'     => $content,
            'is_read'     => false,
        ]);

        $message->load('sender:id,name');

        NewMessage::dispatch(
            $booking->id,
            $sender->id,
            $sender->name,
            $content,
            $message->created_at->toISOString(),
        );

        return $message;
    }
}
