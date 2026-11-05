<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MarkInstalled extends Command
{
    protected $signature = 'install:mark {--force : Overwrite an existing lock file}';

    protected $description = 'Write storage/app/installed.lock so the web installer (/install) stays closed on this deployment';

    public function handle(): int
    {
        $path = storage_path('app/installed.lock');

        if (file_exists($path) && !$this->option('force')) {
            $this->warn("Already marked as installed: {$path}");
            $this->line('Use --force to overwrite the lock file.');

            return self::SUCCESS;
        }

        File::ensureDirectoryExists(dirname($path));

        file_put_contents($path, json_encode([
            'installed_at' => now()->toIso8601String(),
            'app_url' => (string) config('app.url', ''),
            'via' => 'artisan install:mark',
        ], JSON_PRETTY_PRINT) . "\n");

        $this->info("Installed lock written: {$path}");
        $this->line('The /install wizard is now closed on this deployment.');

        return self::SUCCESS;
    }
}
