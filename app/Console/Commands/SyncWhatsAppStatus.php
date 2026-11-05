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
        // Poll EVERY account (#18) — including 'disconnected' ones. The old
        // active/connecting-only filter meant a disconnected account was
        // never polled again, so it could never recover on its own.
        Account::where('platform', 'whatsapp')
            ->each(function ($account) use ($whatsapp) {
                $this->syncOne($account, fn() => $whatsapp->getStatus((string) $account->id), 'WA', [
                    'active' => 'active', 'connected' => 'active', 'open' => 'active',
                    'connecting' => 'connecting', 'qr' => 'connecting',
                    'disconnected' => 'disconnected', 'closed' => 'disconnected',
                    'not_connected' => 'disconnected', 'logged_out' => 'disconnected',
                ]);
            });

        Account::where('platform', 'telegram')
            ->each(function ($account) use ($telegram) {
                $this->syncOne($account, fn() => $telegram->getStatus((string) $account->id), 'TG', [
                    'active' => 'active', 'connected' => 'active',
                    'disconnected' => 'disconnected', 'closed' => 'disconnected',
                    'not_connected' => 'disconnected', 'logged_out' => 'disconnected',
                ]);
            });

        $this->info('Session sync complete.');
    }

    /**
     * Mirror one account's engine status into the DB.
     *
     * 'unreachable' / 'unknown' (and any unrecognised status) NEVER write
     * 'disconnected' (#18): both engine services return the string
     * 'unreachable' when the engine cannot be contacted, so a transient
     * engine outage previously marked every account disconnected — and
     * the old status filter then excluded them from all future polls.
     */
    private function syncOne(Account $account, callable $fetchStatus, string $label, array $statusMap): void
    {
        try {
            $engineStatus = $fetchStatus();
        } catch (\Throwable $e) {
            $this->warn("Account {$account->id} ({$label}) status check failed — leaving status unchanged: {$e->getMessage()}");
            return;
        }

        if ($engineStatus === 'unreachable' || $engineStatus === 'unknown' || $engineStatus === null || $engineStatus === '') {
            $this->warn("Account {$account->id} ({$label}) engine unreachable — leaving status unchanged");
            return;
        }

        $dbStatus = $statusMap[$engineStatus] ?? null;

        if ($dbStatus === null) {
            $this->warn("Account {$account->id} ({$label}) unrecognised engine status '{$engineStatus}' — leaving status unchanged");
            return;
        }

        if ($account->status !== $dbStatus) {
            $old = $account->status;
            $account->update(['status' => $dbStatus]);
            $this->info("Account {$account->id} ({$label}) updated: {$old} → {$dbStatus}");
        }
    }
}
