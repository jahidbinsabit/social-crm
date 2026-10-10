<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PairingCodeReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $accountId,
        public string $code
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('admin-events')];
    }

    public function broadcastWith(): array
    {
        return [
            'accountId' => $this->accountId,
            'code'      => $this->code,
        ];
    }
}
