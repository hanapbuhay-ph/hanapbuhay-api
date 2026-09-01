<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $bookingId,
        public readonly string $role,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly string $recordedAt,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("booking.{$this->bookingId}");
    }

    public function broadcastWith(): array
    {
        return [
            'booking_id'  => $this->bookingId,
            'role'        => $this->role,
            'latitude'    => $this->latitude,
            'longitude'   => $this->longitude,
            'recorded_at' => $this->recordedAt,
        ];
    }
}
