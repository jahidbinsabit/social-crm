<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ResetDailyLimits extends Command
{
    protected $signature = 'accounts:reset-daily-limits';
    protected $description = 'Reset daily message limits for all accounts';

    public function handle(): void
    {
        $today = now()->toDateString();

        // Only reset accounts that haven't been reset today
        $updated = \App\Models\Account::query()
            ->where(function ($q) use ($today) {
                $q->whereNull('last_reset_date')
                  ->orWhere('last_reset_date', '<', $today);
            })
            ->update([
                'messages_sent_today' => 0,
                'last_reset_date'     => $today,
            ]);

        $this->info("Reset daily message counter for {$updated} account(s). Date: {$today}");

        \Illuminate\Support\Facades\Log::info('Daily limits reset', [
            'accounts_reset' => $updated,
            'date'           => $today,
        ]);
    }
}
