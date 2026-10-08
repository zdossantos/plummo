<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class RoomChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public function __construct(private string $code) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('room.'.$this->code);
    }

    public function broadcastAs(): string
    {
        return 'room.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [];
    }
}
