<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class TelegramService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.tg_engine.url', env('TG_ENGINE_URL'));
    }

    private function http(): PendingRequest
    {
        return Http::timeout(60)->baseUrl($this->baseUrl);
    }

    public function requestOtp(string $accountId, string $phoneNumber): array
    {
        $response = $this->http()->post('/session/request-otp', compact('accountId', 'phoneNumber'));

        if (!$response->successful()) {
            return ['status' => 'error', 'message' => $response->body()];
        }

        return $response->json() ?? ['status' => 'error', 'message' => 'Empty response'];
    }

    public function verifyOtp(string $accountId, string $code, ?string $phone2fa = null): array
    {
        $response = $this->http()->post('/session/verify-otp', compact('accountId', 'code', 'phone2fa'));

        if (!$response->successful()) {
            return ['status' => 'error', 'message' => $response->body() ?: 'Engine returned HTTP error ' . $response->status()];
        }

        return $response->json() ?? ['status' => 'error', 'message' => 'Empty response from Telegram engine'];
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

    public function sendMessage(string $accountId, string $username, string $text): array
    {
        return $this->http()
            ->throw()
            ->post('/message/send', compact('accountId', 'username', 'text'))
            ->json() ?? [];
    }

    /**
     * Send an attachment, optionally with a caption.
     *
     * @param  string  $media  base64-encoded file contents
     */
    public function sendMedia(
        string $accountId,
        string $username,
        string $media,
        string $mediaMime,
        string $mediaName,
        string $messageType,
        string $text = ''
    ): array {
        return Http::timeout(120)
            ->baseUrl($this->baseUrl)
            ->throw()
            ->post('/message/send', compact(
                'accountId',
                'username',
                'text',
                'media',
                'mediaMime',
                'mediaName',
                'messageType'
            ))
            ->json() ?? [];
    }

    public function listSessions(): array
    {
        return $this->http()->get('/sessions/list')->json() ?? [];
    }
}
