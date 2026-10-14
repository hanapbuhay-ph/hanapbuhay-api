<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $bookingId,
        public readonly int $senderId,
        public readonly string $senderName,
        public readonly string $message,
        public readonly string $createdAt,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("booking.{$this->bookingId}");
    }

    public function broadcastWith(): array
    {
        return [
            'booking_id' => $this->bookingId,
            'sender'     => ['id' => $this->senderId, 'name' => $this->senderName],
            'message'    => $this->message,
            'created_at' => $this->createdAt,
        ];
    }
}
