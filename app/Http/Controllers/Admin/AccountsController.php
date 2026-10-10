<?php

namespace App\Http\Controllers\Admin;

use App\Events\NewMessageReceived;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Client;
use App\Models\Message;
use App\Services\MediaService;
use App\Services\OutboundMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AccountsController extends Controller
{
    public function index(Request $request)
    {
        $platform = $request->query('platform');
        $accounts = Account::query()
            ->when($platform, fn ($q) => $q->where('platform', $platform))
            ->orderByRaw("FIELD(platform, 'telegram', 'whatsapp')")
            ->orderByDesc('created_at')
            ->get();

        return view('admin.accounts', compact('accounts', 'platform'));
    }

    public function getAccountChats(Account $account): JsonResponse
    {
        $clientIds = Message::where('account_id', $account->id)
            ->distinct()
            ->pluck('client_id');

        if ($clientIds->isEmpty()) {
            return response()->json([]);
        }

        $unreadCounts = Message::where('account_id', $account->id)
            ->whereIn('client_id', $clientIds)
            ->where('is_read', false)
            ->where('sender_type', 'client')
            ->selectRaw('client_id, COUNT(*) as cnt')
            ->groupBy('client_id')
            ->pluck('cnt', 'client_id');

        $lastMsgIds = Message::where('account_id', $account->id)
            ->whereIn('client_id', $clientIds)
            ->selectRaw('client_id, MAX(id) as max_id')
            ->groupBy('client_id')
            ->pluck('max_id', 'client_id');

        $lastMessages = Message::whereIn('id', $lastMsgIds->values())
            ->get(['id', 'client_id', 'message_body', 'message_type', 'sender_type', 'media_name', 'created_at'])
            ->keyBy('client_id');

        $clients = Client::whereIn('id', $clientIds)
            ->select(['id', 'identifier', 'platform', 'status', 'assigned_account_id', 'display_name'])
            ->get()
            ->map(function ($client) use ($unreadCounts, $lastMessages) {
                $lastMsg = $lastMessages->get($client->id);
                $client->last_message     = $lastMsg?->message_body;
                $client->last_message_at  = $lastMsg?->created_at;
                $client->last_sender_type = $lastMsg?->sender_type;
                $client->last_message_type = $lastMsg?->message_type;
                $client->unread_count     = $unreadCounts->get($client->id, 0);
                return $client;
            })
            // Group by identifier — show only the most recent conversation per contact
            ->sortByDesc('last_message_at')
            ->unique('identifier')
            ->values();

        return response()->json($clients);
    }

    public function getClientMessages(Account $account, Client $client, Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', 100));
        $page    = max(1, (int) ($request->query('page', 1)));
        $perPage = max(1, min($perPage, 500));

        $messages = Message::where('account_id', $account->id)
            ->where('client_id', $client->id)
            ->orderBy('created_at', 'asc')
            ->paginate($perPage, [
                'id', 'sender_type', 'message_body', 'message_type',
                'media_path', 'media_mime', 'media_name', 'media_size',
                'is_read', 'created_at', 'staff_id',
            ], 'page', $page);

        // Add media_url to each message so the frontend can render attachments
        $messages->getCollection()->transform(function ($msg) {
            if ($msg->media_path) {
                $msg->media_url = url('/media/' . $msg->media_path);
            }
            return $msg;
        });

        Message::where('account_id', $account->id)
            ->where('client_id', $client->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'messages' => $messages->items(),
            'total' => (int) $messages->total(),
            'page' => (int) $messages->currentPage(),
            'per_page' => (int) $messages->perPage(),
            'last_page' => (int) $messages->lastPage(),
        ]);
    }

    public function sendMessage(
        Request $request,
        Account $account,
        Client $client,
        MediaService $mediaService,
        OutboundMessageService $outbound
    ): JsonResponse {
        $request->validate([
            'message' => 'nullable|string|max:4096|required_without:attachment',
            'attachment' => 'nullable|file|max:' . (MediaService::MAX_BYTES / 1024),
        ]);

        $text = (string) $request->input('message', '');
        $stored = $request->hasFile('attachment')
            ? $mediaService->storeUpload($request->file('attachment'), $account->id)
            : null;

        $savedMessage = Message::create([
            'platform' => $account->platform,
            'account_id' => $account->id,
            'client_id' => $client->id,
            'staff_id' => Auth::id(),
            'sender_type' => 'staff',
            'message_type' => $stored['type'] ?? 'text',
            'message_body' => $text,
            'media_path' => $stored['path'] ?? null,
            'media_mime' => $stored['mime'] ?? null,
            'media_name' => $stored['name'] ?? null,
            'media_size' => $stored['size'] ?? null,
            'is_read' => true,
        ]);

        $deliveryError = null;
        try {
            $outbound->deliver($account, $client, $text, $stored);
        } catch (\Throwable $e) {
            Log::error('Admin reply delivery failed', [
                'account_id' => $account->id,
                'client_id' => $client->id,
                'message_id' => $savedMessage->id,
                'error' => $e->getMessage(),
            ]);
            $deliveryError = 'Message saved but delivery failed. The engine may be offline.';
        }

        // FIXED: Always broadcast so other staff see the message, regardless of engine state
        try {
            broadcast(new NewMessageReceived($savedMessage))->toOthers();
        } catch (\Throwable $broadcastErr) {
            Log::warning('Broadcast failed after admin send', ['error' => $broadcastErr->getMessage()]);
        }

        if ($deliveryError) {
            return response()->json([
                'success' => false,
                'error' => $deliveryError,
                'message' => $savedMessage,
            ], 502);
        }

        return response()->json(['success' => true, 'message' => $savedMessage]);
    }
}
