<?php

namespace App\Providers;

use App\Overrides\Filesystem as OverrideFilesystem;
use App\Services\TelegramService;
use App\Services\WhatsAppService;
use Illuminate\Broadcasting\BroadcastServiceProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WhatsAppService::class);
        $this->app->singleton(TelegramService::class);

        // PHP 8.4 compatibility: override Filesystem::replace() to avoid
        // tempnam() which ignores its directory argument on PHP 8.4.
        $this->app->singleton('files', fn () => new OverrideFilesystem);

        // Register Broadcast provider for WebSocket support (Reverb)
        $this->app->register(BroadcastServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
