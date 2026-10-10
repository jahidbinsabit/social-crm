<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckMemoryUsage extends Command
{
    protected $signature = 'memory:check';
    protected $description = 'Check available system memory and warn if low';

    public function handle(): void
    {
        $memInfo = @file_get_contents('/proc/meminfo');

        if ($memInfo === false) {
            $this->warn('Unable to read /proc/meminfo. This command works on Linux only.');

            return;
        }

        preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $matches);

        if (!isset($matches[1])) {
            $this->warn('MemAvailable not found in /proc/meminfo.');

            return;
        }

        $availableMB = round((int) $matches[1] / 1024);

        $this->info("Available Memory: {$availableMB} MB");

        if ($availableMB < 512) {
            $this->warn('LOW MEMORY WARNING: Less than 512 MB available.');
        } else {
            $this->info('Memory level is healthy.');
        }
    }
}
