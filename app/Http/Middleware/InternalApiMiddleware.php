<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InternalApiMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $secret = $request->header('X-Internal-Secret');

        if ($secret !== config('app.internal_secret')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
