<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminEntityChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $entityType,
        public readonly string $action,
        public readonly int $entityId,
        public readonly ?array $payload = null,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('admin-panel');
    }

    public function broadcastWith(): array
    {
        return [
            'entity_type' => $this->entityType,
            'action' => $this->action,
            'entity_id' => $this->entityId,
            'payload' => $this->payload,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
