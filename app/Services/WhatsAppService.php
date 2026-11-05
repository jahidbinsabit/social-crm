<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.wa_engine.url', env('WA_ENGINE_URL'));
    }

    private function http(int $timeout = 10): PendingRequest
    {
        return Http::timeout($timeout)
            ->baseUrl($this->baseUrl)
            ->withHeaders(['X-Internal-Secret' => (string) config('app.internal_secret')]);
    }

    public function startSession(string $accountId, string $phoneNumber): array
    {
        return $this->http()->post('/session/start', compact('accountId', 'phoneNumber'))->json();
    }

    public function startPairing(string $accountId, string $phoneNumber): array
    {
        return $this->http()->post('/session/start', [
            'accountId' => $accountId,
            'phoneNumber' => $phoneNumber,
            'mode' => 'pairing',
        ])->json();
    }

    public function getPairingStatus(string $accountId): array
    {
        try {
            return $this->http()->get("/session/status/{$accountId}")->json() ?? [];
        } catch (\Throwable $e) {
            return ['status' => 'unreachable', 'error' => $e->getMessage()];
        }
    }

    public function getQrCode(string $accountId): \Illuminate\Http\Client\Response
    {
        return $this->http()->get("/session/qr/{$accountId}");
    }

    public function disconnectSession(string $accountId): array
    {
        try {
            return $this->http()->post('/session/disconnect', compact('accountId'))->json() ?? [];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function getStatus(string $accountId): string
    {
        try {
            return $this->http()->get("/session/status/{$accountId}")->json('status', 'unknown') ?? 'unknown';
        } catch (\Throwable $e) {
            return 'unreachable';
        }
    }

    public function sendMessage(string $accountId, string $jid, string $text): array
    {
        // Strip only the individual-user suffix — group JIDs (@g.us) must
        // pass through untouched or the send is misdirected to an individual.
        $cleanJid = str_ends_with($jid, '@s.whatsapp.net')
            ? substr($jid, 0, -strlen('@s.whatsapp.net'))
            : $jid;

        // WhatsApp can be slow on the first send after reconnect because Baileys
        // runs USync lookups. Use the same generous timeout as media sends.
        return $this->http(60)
            ->throw()
            ->post('/message/send', [
                'accountId' => $accountId,
                'jid'       => $cleanJid,
                'text'      => $text,
            ])
            ->json() ?? [];
    }

    /**
     * Send an attachment, optionally with a caption.
     *
     * @param  string  $media  base64-encoded file contents
     */
    public function sendMedia(
        string $accountId,
        string $jid,
        string $media,
        string $mediaMime,
        string $mediaName,
        string $messageType,
        string $text = ''
    ): array {
        // Same rule as sendMessage(): strip only @s.whatsapp.net, keep @g.us.
        $cleanJid = str_ends_with($jid, '@s.whatsapp.net')
            ? substr($jid, 0, -strlen('@s.whatsapp.net'))
            : $jid;

        return $this->http(120)
            ->throw()
            ->post('/message/send', [
                'accountId'   => $accountId,
                'jid'         => $cleanJid,
                'text'        => $text,
                'media'       => $media,
                'mediaMime'   => $mediaMime,
                'mediaName'   => $mediaName,
                'messageType' => $messageType,
            ])
            ->json() ?? [];
    }

    public function listSessions(): array
    {
        return $this->http()->get('/sessions/list')->json() ?? [];
    }
}
