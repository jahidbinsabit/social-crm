<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('accounts:reset-daily-limits')->dailyAt('00:00');
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('sessions:sync-status')->everyFiveMinutes();
Schedule::command('queue:prune-failed --hours=72')->daily();
