<?php

namespace App\Events;

use App\Models\Account;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QrCodeReady implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $accountId;

    /**
     * Create a new event instance.
     */
    public function __construct(string $accountId)
    {
        $this->accountId = $accountId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin-events'),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * Minimal by design (unchanged payload): only the account id — the QR
     * image itself is fetched by the admin UI over authenticated HTTP.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'accountId' => $this->accountId,
        ];
    }
}
