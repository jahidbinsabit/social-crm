<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    /** Hard ceiling per file — WhatsApp itself caps documents around 100 MB. */
    public const MAX_BYTES = 64 * 1024 * 1024;

    /**
     * Map a MIME type to the message_type enum used by the messages table.
     */
    public static function typeFromMime(?string $mime): string
    {
        $mime = strtolower((string) $mime);

        return match (true) {
            str_starts_with($mime, 'image/webp') => 'sticker',
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'document',
        };
    }

    /**
     * Store an uploaded attachment on the private disk.
     *
     * @return array{path:string,mime:string,name:string,size:int,type:string}
     */
    public function storeUpload(UploadedFile $file, int $accountId): array
    {
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
        $filename = Str::uuid() . '.' . $extension;
        $directory = 'media/' . $accountId . '/' . date('Y-m');

        $path = $file->storeAs($directory, $filename, 'local');

        return [
            'path' => $path,
            'mime' => $mime,
            'name' => $file->getClientOriginalName() ?: $filename,
            'size' => $file->getSize() ?: 0,
            'type' => self::typeFromMime($mime),
        ];
    }

    /**
     * Persist base64 media pushed by an engine webhook (inbound message).
     *
     * @return array{path:string,mime:string,name:string,size:int,type:string}|null
     */
    public function storeBase64(string $base64, ?string $mime, ?string $name, int $accountId): ?array
    {
        // Engines may send a data: URI or a bare base64 payload.
        if (str_contains($base64, ',') && str_starts_with($base64, 'data:')) {
            [$header, $base64] = explode(',', $base64, 2);
            if (!$mime && preg_match('#data:([^;]+)#', $header, $m)) {
                $mime = $m[1];
            }
        }

        $binary = base64_decode($base64, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        if (strlen($binary) > self::MAX_BYTES) {
            return null;
        }

        $mime = $mime ?: 'application/octet-stream';
        $extension = self::extensionFromMime($mime, $name);
        $filename = Str::uuid() . '.' . $extension;
        $path = 'media/' . $accountId . '/' . date('Y-m') . '/' . $filename;

        Storage::disk('local')->put($path, $binary);

        return [
            'path' => $path,
            'mime' => $mime,
            'name' => $name ?: $filename,
            'size' => strlen($binary),
            'type' => self::typeFromMime($mime),
        ];
    }

    /**
     * Read stored media back as base64 so it can be handed to an engine.
     */
    public function toBase64(string $path): ?string
    {
        if (!Storage::disk('local')->exists($path)) {
            return null;
        }

        return base64_encode(Storage::disk('local')->get($path));
    }

    private static function extensionFromMime(string $mime, ?string $name): string
    {
        if ($name && ($ext = pathinfo($name, PATHINFO_EXTENSION))) {
            return strtolower($ext);
        }

        return match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            'video/webm' => 'webm',
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/mp4', 'audio/m4a' => 'm4a',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            default => 'bin',
        };
    }
}
