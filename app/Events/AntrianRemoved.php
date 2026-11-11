<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AntrianRemoved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $orderId,
        public readonly int $cabangId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('antrian.' . $this->cabangId),
            new Channel('tv-antrian.' . $this->cabangId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'antrian.removed';
    }

    public function broadcastWith(): array
    {
        return ['order_id' => $this->orderId];
    }
}
