<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TestPusherEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $message,
        public readonly string $time,
        public readonly string $firedBy,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('test-pusher');
    }

    public function broadcastAs(): string
    {
        return 'test.message';
    }

    public function broadcastWith(): array
    {
        return [
            'message'   => $this->message,
            'time'      => $this->time,
            'fired_by'  => $this->firedBy,
        ];
    }
}
