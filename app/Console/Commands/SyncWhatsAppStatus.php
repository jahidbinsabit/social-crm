<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;
use App\Services\WhatsAppService;
use App\Services\TelegramService;

class SyncWhatsAppStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sessions:sync-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync account session statuses from WhatsApp/Telegram engines to the database';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppService $whatsapp, TelegramService $telegram): void
    {
        // Sync WhatsApp sessions
        Account::where('platform', 'whatsapp')
            ->whereIn('status', ['active', 'connecting'])
            ->each(function ($account) use ($whatsapp) {
                try {
                    $engineStatus = $whatsapp->getStatus((string) $account->id);
                    $dbStatus = match($engineStatus) {
                        'active', 'connected', 'open' => 'active',
                        'connecting', 'qr' => 'connecting',
                        default => 'disconnected',
                    };

                    if ($account->status !== $dbStatus) {
                        $account->update(['status' => $dbStatus]);
                        $this->info("Account {$account->id} (WA) updated: {$account->status} → {$dbStatus}");
                    }
                } catch (\Throwable $e) {
                    if ($account->status !== 'disconnected') {
                        $account->update(['status' => 'disconnected']);
                        $this->warn("Account {$account->id} (WA) engine unreachable — marked disconnected");
                    }
                }
            });

        // Sync Telegram sessions
        Account::where('platform', 'telegram')
            ->whereIn('status', ['active', 'connecting'])
            ->each(function ($account) use ($telegram) {
                try {
                    $engineStatus = $telegram->getStatus((string) $account->id);
                    $dbStatus = ($engineStatus === 'active') ? 'active' : 'disconnected';

                    if ($account->status !== $dbStatus) {
                        $account->update(['status' => $dbStatus]);
                        $this->info("Account {$account->id} (TG) updated: {$account->status} → {$dbStatus}");
                    }
                } catch (\Throwable $e) {
                    if ($account->status !== 'disconnected') {
                        $account->update(['status' => 'disconnected']);
                        $this->warn("Account {$account->id} (TG) engine unreachable — marked disconnected");
                    }
                }
            });

        $this->info('Session sync complete.');
    }
}
