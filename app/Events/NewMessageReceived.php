<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NewMessageReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;

    /**
     * Create a new event instance.
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'NewMessageReceived';
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [new Channel('admin-events')];

        try {
            // Single optimized query — get all staff IDs for this account
            $staffIds = DB::table('account_staff')
                ->where('account_id', $this->message->account_id)
                ->pluck('user_id');

            foreach ($staffIds->unique() as $id) {
                if ($id) {
                    $channels[] = new PrivateChannel('staff.' . $id);
                }
            }

            // If the sending staff member is not in the account assignment,
            // still notify them (e.g. admin replies from Accounts tab)
            if ($this->message->staff_id && !$staffIds->contains($this->message->staff_id)) {
                $channels[] = new PrivateChannel('staff.' . $this->message->staff_id);
            }
        } catch (\Throwable $e) {
            // Never let a broadcast channel error prevent the event from firing
            Log::warning('NewMessageReceived channel error', [
                'error' => $e->getMessage(),
                'message_id' => $this->message->id,
            ]);
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $message = $this->message->loadMissing(['client', 'account']);

        // Build an accessible URL for any media attachment so the browser
        // can render it immediately on WebSocket delivery.
        $mediaUrl = null;
        if ($message->media_path) {
            $mediaUrl = url('/media/' . $message->media_path);
        }

        return [
            'message' => array_merge($message->toArray(), [
                'media_url' => $mediaUrl,
            ]),
            'account_id' => $message->account_id,
            'client_id'  => $message->client_id,
        ];
    }
}
