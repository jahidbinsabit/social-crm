<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
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
        // PrivateChannel: a pairing code is a live linking credential — it
        // must only ever reach authenticated admins, never public listeners.
        return [new PrivateChannel('admin-events')];
    }

    public function broadcastWith(): array
    {
        return [
            'accountId' => $this->accountId,
            'code'      => $this->code,
        ];
    }
}
