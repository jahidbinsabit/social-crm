<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InternalApiMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $configured = (string) config('app.internal_secret', '');

        // Fail closed: an empty configured secret must never authenticate
        // anyone — not even a request that sends an empty header back.
        // Previously '' !== comparison let an empty header pass whenever
        // INTERNAL_API_SECRET was missing from .env.
        if ($configured === '') {
            return response()->json(['error' => 'Internal secret not configured'], 503);
        }

        $provided = (string) $request->header('X-Internal-Secret', '');

        if (!hash_equals($configured, $provided)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
