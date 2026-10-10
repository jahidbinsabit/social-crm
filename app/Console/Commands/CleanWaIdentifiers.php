<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;

class CleanWaIdentifiers extends Command
{
    protected $signature = 'app:clean-wa-identifiers';
    protected $description = 'Strip @s.whatsapp.net suffixes from existing WhatsApp client identifiers';

    public function handle(): void
    {
        $clients = Client::where('platform', 'whatsapp')
            ->where('identifier', 'LIKE', '%@%')
            ->get();

        $count = 0;
        foreach ($clients as $client) {
            $clean = explode('@', $client->identifier)[0];

            $duplicate = Client::where('platform', 'whatsapp')
                ->where('identifier', $clean)
                ->whereNull('campaign_id')
                ->where('id', '!=', $client->id)
                ->first();

            if (!$duplicate) {
                $client->update(['identifier' => $clean]);
                $count++;
            } else {
                $this->warn("Duplicate found for {$clean}, skipping {$client->id}");
            }
        }

        $this->info("Cleaned {$count} WhatsApp identifiers.");
    }
}
