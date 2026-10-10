<?php

namespace App\Http\Controllers\Api;

use App\Events\AccountStatusChanged;
use App\Events\NewMessageReceived;
use App\Events\QrCodeReady;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Client;
use App\Models\Message;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InternalEventController extends Controller
{
    public function waIncoming(Request $request, MediaService $media): JsonResponse
    {
        return $this->storeIncoming($request, $media, 'whatsapp');
    }

    public function tgIncoming(Request $request, MediaService $media): JsonResponse
    {
        return $this->storeIncoming($request, $media, 'telegram');
    }

    /**
     * Persist an inbound message from either engine.
     *
     * A message carries text, an attachment, or both — so the body is only
     * required when no media accompanies it.
     */
    private function storeIncoming(Request $request, MediaService $media, string $platform): JsonResponse
    {
        $validated = $request->validate([
            'accountId' => 'required|string',
            'from' => 'required|string',
            'senderId' => 'nullable|string',
            'phone' => 'nullable|string',
            'displayName' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'timestamp' => 'nullable|string',
            'messageType' => 'nullable|string|in:text,image,video,audio,document,sticker',
            'media' => 'nullable|string',
            'mediaMime' => 'nullable|string|max:128',
            'mediaName' => 'nullable|string|max:255',
        ]);

        $bodyText = trim($validated['body'] ?? '');
        $hasMedia = !empty($validated['media']);

        if ($bodyText === '' && !$hasMedia) {
            return response()->json(['error' => 'Empty message — nothing to store'], 422);
        }

        $account = Account::where('id', $validated['accountId'])
            ->where('platform', $platform)
            ->first();

        if (!$account) {
            return response()->json(['error' => 'Account not found'], 404);
        }

        // Normalize identifiers so the same person always maps to one record.
        // - Telegram: strip leading @ from usernames
        // - WhatsApp: strip JID suffix (@s.whatsapp.net)
        $cleanIdentifier = $validated['from'];
        if ($platform === 'telegram' && str_starts_with($cleanIdentifier, '@')) {
            $cleanIdentifier = substr($cleanIdentifier, 1);
        }
        if ($platform === 'whatsapp' && str_contains($cleanIdentifier, '@')) {
            $cleanIdentifier = explode('@', $cleanIdentifier, 2)[0];
        }

        $displayName = $validated['displayName'] ?? null;
        if ($displayName && in_array(strtolower(trim($displayName)), ['client', 'contact', 'crm contact', 'user', 'unknown', 'none', 'null'], true)) {
            $displayName = null;
        }

        $senderId = $validated['senderId'] ?? null;
        $phone = $validated['phone'] ?? null;

        // Build list of candidate identifiers to match existing client records
        $candidates = array_filter(array_unique([
            $cleanIdentifier,
            str_starts_with($cleanIdentifier, '+') ? substr($cleanIdentifier, 1) : '+' . $cleanIdentifier,
            $senderId,
            $phone,
            $phone ? ltrim($phone, '+') : null,
            $phone ? '+' . ltrim($phone, '+') : null,
        ]));

        // Find all clients matching any candidate identifier
        $clients = Client::where('platform', $platform)
            ->whereIn('identifier', $candidates)
            ->get();

        if ($clients->isEmpty()) {
            $client = null;
        } else {
            // Prefer the client assigned to this account; otherwise take the most recent
            $client = $clients->where('assigned_account_id', $account->id)->first();
            if (!$client) {
                $client = $clients->sortByDesc(function ($c) {
                    return Message::where('client_id', $c->id)->max('created_at') ?? $c->created_at;
                })->first();
            }
        }

        if (!$client) {
            $client = Client::create([
                'campaign_id' => null,
                'platform' => $platform,
                'identifier' => $cleanIdentifier,
                'display_name' => $displayName ?: $cleanIdentifier,
                'status' => 'pending',
                'assigned_account_id' => $account->id,
            ]);
        } else {
            // Update display name if it changed and is valid
            if ($displayName && $client->display_name !== $displayName) {
                $client->update(['display_name' => $displayName]);
            } elseif ($client->display_name && in_array(strtolower(trim($client->display_name)), ['client', 'contact', 'crm contact', 'user', 'unknown', 'none', 'null'], true)) {
                $client->update(['display_name' => $displayName ?: $cleanIdentifier]);
            }
            // If client was from another account and has no assignment, link it
            if (!$client->assigned_account_id) {
                $client->update(['assigned_account_id' => $account->id]);
            }
        }

        // Only auto-assign if the client has no assigned account yet.
        if (!$client->assigned_account_id) {
            $client->update(['assigned_account_id' => $account->id]);
        }

        $attributes = [
            'platform' => $platform,
            'account_id' => $account->id,
            'client_id' => $client->id,
            'sender_type' => 'client',
            'message_type' => $validated['messageType'] ?? 'text',
            'message_body' => $bodyText,
            'is_read' => false,
        ];

        if ($hasMedia) {
            $stored = $media->storeBase64(
                $validated['media'],
                $validated['mediaMime'] ?? null,
                $validated['mediaName'] ?? null,
                $account->id
            );

            if ($stored) {
                $attributes['media_path'] = $stored['path'];
                $attributes['media_mime'] = $stored['mime'];
                $attributes['media_name'] = $stored['name'];
                $attributes['media_size'] = $stored['size'];
                $attributes['message_type'] = $validated['messageType'] ?? $stored['type'];
            } else {
                Log::warning('Inbound media could not be stored', [
                    'platform' => $platform,
                    'account_id' => $account->id,
                    'mime' => $validated['mediaMime'] ?? 'unknown',
                    'size_hint' => strlen($validated['media']),
                ]);
                $attributes['message_type'] = 'text';
                $attributes['message_body'] = $bodyText ?: '[media attachment — could not be saved]';
            }
        }

        $message = Message::create($attributes);

        broadcast(new NewMessageReceived($message));

        return response()->json(['ok' => true]);
    }

    public function waQrReady(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'accountId' => 'required|string',
        ]);

        $account = Account::where('id', $validated['accountId'])
            ->where('platform', 'whatsapp')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'Account not found'], 404);
        }

        $account->update(['status' => 'connecting']);

        broadcast(new QrCodeReady($account->id))->toOthers();

        return response()->json(['ok' => true]);
    }

    public function waEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'accountId' => 'required|string',
            'event'     => 'required|string|in:connected,disconnected,pairing_code,error',
            'code'      => 'nullable|string',
            'message'   => 'nullable|string',
        ]);

        $account = Account::where('id', $validated['accountId'])
            ->where('platform', 'whatsapp')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'Account not found'], 404);
        }

        // Handle pairing code delivery to admin UI
        if ($validated['event'] === 'pairing_code') {
            // Update account name with code for display
            $account->update(['status' => 'connecting']);

            // Broadcast pairing code to admin panel
            broadcast(new \App\Events\PairingCodeReceived(
                $account->id,
                $validated['code'] ?? ''
            ))->toOthers();

            return response()->json(['ok' => true]);
        }

        // Handle error from engine
        if ($validated['event'] === 'error') {
            $account->update(['status' => 'disconnected']);
            broadcast(new AccountStatusChanged($account))->toOthers();
            return response()->json(['ok' => true]);
        }

        // connected or disconnected
        $account->update([
            'status' => $validated['event'] === 'connected' ? 'active' : 'disconnected',
        ]);

        broadcast(new AccountStatusChanged($account))->toOthers();

        return response()->json(['ok' => true]);
    }

    public function tgEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'accountId' => 'required|string',
            'event' => 'required|string|in:connected,disconnected',
        ]);

        $account = Account::where('id', $validated['accountId'])
            ->where('platform', 'telegram')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'Account not found'], 404);
        }

        $account->update([
            'status' => $validated['event'] === 'connected' ? 'active' : 'disconnected',
        ]);

        broadcast(new AccountStatusChanged($account))->toOthers();

        return response()->json(['ok' => true]);
    }
}
