<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Client;

class OutboundMessageService
{
    public function __construct(
        private WhatsAppService $whatsapp,
        private TelegramService $telegram,
        private MediaService $media,
    ) {
    }

    /**
     * Hand an outbound message to the engine that owns this account's platform.
     *
     * @param  array{path:string,mime:string,name:string,size:int,type:string}|null  $stored
     *
     * @throws \Throwable when the engine rejects or cannot be reached
     */
    public function deliver(Account $account, Client $client, string $text, ?array $stored = null): void
    {
        $isWhatsApp = $account->platform === 'whatsapp';

        if (!$stored) {
            $isWhatsApp
                ? $this->whatsapp->sendMessage((string) $account->id, $client->identifier, $text)
                : $this->telegram->sendMessage((string) $account->id, $client->identifier, $text);

            return;
        }

        $base64 = $this->media->toBase64($stored['path']);
        if ($base64 === null) {
            throw new \RuntimeException('Stored attachment could not be read back from disk');
        }

        $args = [
            (string) $account->id,
            $client->identifier,
            $base64,
            $stored['mime'],
            $stored['name'],
            $stored['type'],
            $text,
        ];

        $isWhatsApp
            ? $this->whatsapp->sendMedia(...$args)
            : $this->telegram->sendMedia(...$args);
    }
}
