<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LocationTrackingStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $bookingId,
        public readonly string $role,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("booking.{$this->bookingId}");
    }

    public function broadcastWith(): array
    {
        return [
            'booking_id' => $this->bookingId,
            'role'       => $this->role,
        ];
    }
}
