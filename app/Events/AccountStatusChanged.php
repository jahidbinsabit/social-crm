<?php

namespace App\Events;

use App\Models\Account;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AccountStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Account $account;

    /**
     * Create a new event instance.
     */
    public function __construct(Account $account)
    {
        $this->account = $account;
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
     * Explicit whitelist: without this the public $account property is
     * serialized in full — including the account's session_data (live
     * WhatsApp/Telegram credentials). Only the fields the admin UI needs
     * to update an account row are ever sent.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'account' => [
                'id' => $this->account->id,
                'platform' => $this->account->platform,
                'phone_number' => $this->account->phone_number,
                'status' => $this->account->status,
            ],
        ];
    }
}
