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
            'externalId' => 'nullable|string|max:191',
            'messageId' => 'nullable|string|max:191',
        ]);

        // Engine message id for dedupe (#14). Payloads may name it
        // externalId, messageId, or a bare id.
        $externalId = $validated['externalId'] ?? $validated['messageId'] ?? null;
        if (!$externalId && $request->filled('id') && is_scalar($request->input('id'))) {
            $externalId = (string) $request->input('id');
        }
        $externalId = $externalId !== null && trim($externalId) !== '' ? trim($externalId) : null;

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

        // Dedupe (#14): a retry of an already-stored engine message is a
        // success, not a second copy — ack it without storing/broadcasting.
        if ($externalId && Message::where('account_id', $account->id)->where('external_id', $externalId)->exists()) {
            return response()->json(['ok' => true, 'duplicate' => true]);
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

        $junkNames = ['client', 'contact', 'crm contact', 'user', 'unknown', 'none', 'null'];

        $displayName = $validated['displayName'] ?? null;
        if ($displayName && in_array(strtolower(trim($displayName)), $junkNames, true)) {
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

        // Find this account's OWN client matching any candidate identifier
        // (#13). A conversation belongs to one account: the same person
        // messaging two business numbers gets two separate client records.
        // Another account's client is NEVER merged into — if this account
        // has no matching client, a fresh one is created below.
        $client = Client::where('platform', $platform)
            ->whereIn('identifier', $candidates)
            ->where(function ($q) use ($account) {
                $q->where('account_id', $account->id)
                    ->orWhere('assigned_account_id', $account->id);
            })
            ->get()
            ->sortByDesc(function ($c) {
                return Message::where('client_id', $c->id)->max('created_at') ?? $c->created_at;
            })
            ->first();

        if (!$client) {
            $client = Client::create([
                'campaign_id' => null,
                'account_id' => $account->id,
                'platform' => $platform,
                'identifier' => $cleanIdentifier,
                'display_name' => $displayName ?: $cleanIdentifier,
                'status' => 'pending',
                'assigned_account_id' => $account->id,
            ]);
        } else {
            // Display name (#15): an inbound push name may only fill in a
            // name that is empty, still the raw identifier, or a known
            // placeholder — a real stored name is never overwritten.
            $currentName = $client->display_name;
            $nameIsPlaceholder = !$currentName
                || $currentName === $client->identifier
                || in_array(strtolower(trim($currentName)), $junkNames, true);
            if ($displayName && $nameIsPlaceholder && $currentName !== $displayName) {
                $client->update(['display_name' => $displayName]);
            }

            // Backfill the #13 ownership columns for pre-migration rows of
            // THIS account (the lookup above already scoped to it).
            $link = [];
            if (!$client->account_id) {
                $link['account_id'] = $account->id;
            }
            if (!$client->assigned_account_id) {
                $link['assigned_account_id'] = $account->id;
            }
            if ($link) {
                $client->update($link);
            }
        }

        $attributes = [
            'platform' => $platform,
            'account_id' => $account->id,
            'client_id' => $client->id,
            'external_id' => $externalId,
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

        if ($externalId) {
            // firstOrCreate on the unique (account_id, external_id): even if
            // two retries race past the early check above, only one row and
            // one broadcast ever happen (#14).
            try {
                $message = Message::firstOrCreate(
                    ['account_id' => $account->id, 'external_id' => $externalId],
                    $attributes
                );
            } catch (\Illuminate\Database\QueryException $e) {
                return response()->json(['ok' => true, 'duplicate' => true]);
            }

            if (!$message->wasRecentlyCreated) {
                return response()->json(['ok' => true, 'duplicate' => true]);
            }
        } else {
            $message = Message::create($attributes);
        }

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
