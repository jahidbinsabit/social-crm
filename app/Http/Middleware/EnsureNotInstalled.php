<?php

namespace App\Http\Middleware;

use App\Services\InstallerService;
use Closure;
use Illuminate\Http\Request;

class EnsureNotInstalled
{
    public function handle(Request $request, Closure $next)
    {
        if (app(InstallerService::class)->isInstalled()) {
            return redirect('/login');
        }

        return $next($request);
    }
}
