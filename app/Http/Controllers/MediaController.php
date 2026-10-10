<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    /**
     * Stream chat media to an authenticated user.
     *
     * Media lives on the private disk rather than public/storage — conversation
     * attachments must not be reachable by guessing a URL.
     */
    public function show(Request $request, string $path): StreamedResponse
    {
        // Decode any URL-encoded characters in the path
        $path = rawurldecode($path);

        // Security: reject path traversal and paths outside media/
        if (str_contains($path, '..') || !str_starts_with($path, 'media/')) {
            abort(404);
        }

        // Additional security: ensure no null bytes
        if (str_contains($path, "\0")) {
            abort(404);
        }

        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        // Authorization: staff may only view media for accounts they are assigned to
        if ($user->role !== 'admin') {
            $message = \App\Models\Message::where('media_path', $path)->first();
            if ($message) {
                $hasAccess = \Illuminate\Support\Facades\DB::table('account_staff')
                    ->where('account_id', $message->account_id)
                    ->where('user_id', $user->id)
                    ->exists();

                if (!$hasAccess) {
                    abort(403, 'Unauthorized access to media');
                }
            }
        }

        if (!Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $mime = Storage::disk('local')->mimeType($path) ?: 'application/octet-stream';
        $size = Storage::disk('local')->size($path);

        return response()->stream(
            function () use ($path) {
                $stream = Storage::disk('local')->readStream($path);
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type'        => $mime,
                'Content-Length'      => $size,
                'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
                'Cache-Control'       => 'private, max-age=86400',
                'Accept-Ranges'       => 'bytes',
            ]
        );
    }
}
