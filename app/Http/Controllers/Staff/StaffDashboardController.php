<?php

namespace App\Http\Controllers\Staff;

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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StaffDashboardController extends Controller
{
    public function index(Request $request)
    {
        $channel = session('channel'); // 'telegram' or 'whatsapp'

        if (!$channel) {
            return redirect()->route('staff.channel');
        }

        $accounts = $request->user()
            ->accounts()
            ->where('platform', $channel)
            ->get();

        // For each account, get unread count + last message
        $accounts = $accounts->map(function ($account) {
            $account->unread_count = Message::where('account_id', $account->id)
                ->where('is_read', false)
                ->where('sender_type', 'client')
                ->count();

            $lastMsg = Message::where('account_id', $account->id)
                ->latest()
                ->first();
            $account->last_message = $lastMsg?->message_body;
            $account->last_message_at = $lastMsg?->created_at;

            return $account;
        });

        return view('staff.dashboard', compact('accounts', 'channel'));
    }

    /**
     * Get all clients (conversations) for an account, with last message preview.
     * Filtered by the session channel platform for extra safety.
     */
    public function getAccountChats(Account $account, Request $request): JsonResponse
    {
        $user    = Auth::user();
        $channel = session('channel');

        if (!$user->accounts()->where('accounts.id', $account->id)->exists()) {
            abort(403);
        }
        if ($channel && $account->platform !== $channel) {
            abort(403);
        }

        $perPage = (int) ($request->query('per_page', 50));
        $page    = max(1, (int) ($request->query('page', 1)));
        $perPage = max(1, min($perPage, 200));
        $filter  = strtolower((string) $request->query('filter', '')) === 'unread' ? 'unread' : 'all';

        $clientQuery = DB::table('messages')
            ->where('account_id', $account->id);

        if ($filter === 'unread') {
            $clientQuery->where('is_read', false)
                ->where('sender_type', 'client');
        }

        // Count unique clients (not messages) for pagination metadata.
        $totalClients = (clone $clientQuery)
            ->distinct('client_id')
            ->count('client_id');

        // DB-level pagination of client IDs, ordered by most recent conversation.
        $clientIds = $clientQuery
            ->select('client_id')
            ->distinct()
            ->orderByRaw('MAX(created_at) DESC')
            ->groupBy('client_id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->pluck('client_id');

        if ($clientIds->isEmpty()) {
            return response()->json([
                'data' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => 0,
            ]);
        }

        // Batch unread counts — one query for this page only
        $unreadCounts = Message::where('account_id', $account->id)
            ->whereIn('client_id', $clientIds)
            ->where('is_read', false)
            ->where('sender_type', 'client')
            ->selectRaw('client_id, COUNT(*) as cnt')
            ->groupBy('client_id')
            ->pluck('cnt', 'client_id');

        // Batch last messages — one query for this page using subquery
        $lastMsgIds = Message::where('account_id', $account->id)
            ->whereIn('client_id', $clientIds)
            ->selectRaw('client_id, MAX(id) as max_id')
            ->groupBy('client_id')
            ->pluck('max_id', 'client_id');

        $lastMessages = Message::whereIn('id', $lastMsgIds->values())
            ->get(['id', 'client_id', 'message_body', 'message_type', 'sender_type', 'is_read', 'created_at', 'media_name'])
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
                $client->last_media_name  = $lastMsg?->media_name;
                $client->unread_count     = $unreadCounts->get($client->id, 0);
                return $client;
            })
            ->sortByDesc('last_message_at')
            ->values();

        return response()->json([
            'data' => $clients,
            'total' => (int) $totalClients,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($totalClients / $perPage),
        ]);
    }

    /**
     * Get all messages for a specific client conversation.
     * Marks messages as read.
     */
    public function getClientMessages(Account $account, Client $client, Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user->accounts()->where('accounts.id', $account->id)->exists()) {
            abort(403);
        }

        $channel = session('channel');
        if ($channel && $account->platform !== $channel) {
            abort(403);
        }

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
        // without having to reconstruct the URL itself.
        $messages->getCollection()->transform(function ($msg) {
            if ($msg->media_path) {
                $msg->media_url = url('/media/' . $msg->media_path);
            }
            return $msg;
        });

        // Mark all as read
        Message::where('account_id', $account->id)
            ->where('client_id', $client->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'messages' => $messages->items(),
            'client' => $client->only(['id', 'identifier', 'platform']),
            'account' => $account->only(['id', 'account_name', 'phone_number', 'platform']),
            'total' => (int) $messages->total(),
            'page' => (int) $messages->currentPage(),
            'per_page' => (int) $messages->perPage(),
            'last_page' => (int) $messages->lastPage(),
        ]);
    }

    /**
     * Send a message to a client.
     */
    public function sendMessage(
        Request $request,
        Account $account,
        Client $client,
        MediaService $mediaService,
        OutboundMessageService $outbound
    ): JsonResponse {
        $user = Auth::user();
        if (!$user->accounts()->where('accounts.id', $account->id)->exists()) {
            abort(403);
        }

        $request->validate([
            // Either a caption or an attachment must be present.
            'message' => 'nullable|string|max:4096|required_without:attachment',
            'attachment' => 'nullable|file|max:' . (MediaService::MAX_BYTES / 1024),
        ]);

        $text = (string) $request->input('message', '');
        $stored = null;

        if ($request->hasFile('attachment')) {
            $stored = $mediaService->storeUpload($request->file('attachment'), $account->id);
        }

        $savedMessage = Message::create([
            'platform'     => $account->platform,
            'account_id'   => $account->id,
            'client_id'    => $client->id,
            'staff_id'     => Auth::id(),
            'sender_type'  => 'staff',
            'message_type' => $stored['type'] ?? 'text',
            'message_body' => $text,
            'media_path'   => $stored['path'] ?? null,
            'media_mime'   => $stored['mime'] ?? null,
            'media_name'   => $stored['name'] ?? null,
            'media_size'   => $stored['size'] ?? null,
            'is_read'      => true,
        ]);

        $deliveryError = null;
        try {
            $outbound->deliver($account, $client, $text, $stored);
        } catch (\Throwable $e) {
            Log::error('Staff reply delivery failed', [
                'account_id' => $account->id,
                'client_id'  => $client->id,
                'error'      => $e->getMessage(),
            ]);
            $deliveryError = 'Message saved but not delivered. Engine may be offline.';
        }

        // FIXED: Always broadcast so other staff see the message, regardless of engine state
        try {
            broadcast(new NewMessageReceived($savedMessage))->toOthers();
        } catch (\Throwable $broadcastErr) {
            Log::warning('Broadcast failed after staff send', ['error' => $broadcastErr->getMessage()]);
        }

        if ($deliveryError) {
            return response()->json([
                'success' => false,
                'error'   => $deliveryError,
                'message' => $savedMessage,
            ], 502);
        }

        return response()->json(['success' => true, 'message' => $savedMessage]);
    }
}