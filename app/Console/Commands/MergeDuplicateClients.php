<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeDuplicateClients extends Command
{
    protected $signature = 'clients:merge-duplicates {--dry-run : Show what would be merged without doing it}';
    protected $description = 'Merge duplicate client records (same identifier+platform) into a single conversation thread';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Find identifiers that have multiple client records
        $duplicates = Client::select('identifier', 'platform')
            ->groupBy('identifier', 'platform')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('No duplicate clients found.');
            return 0;
        }

        $this->info("Found {$duplicates->count()} identifiers with duplicate clients.");

        $mergedCount = 0;
        $movedMessages = 0;

        foreach ($duplicates as $dup) {
            $clients = Client::where('identifier', $dup->identifier)
                ->where('platform', $dup->platform)
                ->get();

            // Pick the survivor: the one with the most recent message (or most recent created_at)
            $survivor = $clients->sortByDesc(function ($c) {
                $lastMsg = Message::where('client_id', $c->id)->max('created_at');
                return $lastMsg ?? $c->created_at;
            })->first();

            $others = $clients->where('id', '!=', $survivor->id);

            $this->line("");
            $this->line("Identifier: {$dup->identifier} ({$dup->platform})");
            $this->line("  Survivor: client #{$survivor->id} (campaign {$survivor->campaign_id})");

            foreach ($others as $other) {
                $msgCount = Message::where('client_id', $other->id)->count();
                $this->line("  Merge:    client #{$other->id} (campaign {$other->campaign_id}) — {$msgCount} messages");

                if (!$dryRun) {
                    // Move all messages to survivor
                    $moved = Message::where('client_id', $other->id)
                        ->update(['client_id' => $survivor->id]);
                    $movedMessages += $moved;

                    // Delete the duplicate (messages already moved to survivor)
                    // We skip campaign_id updates — the unique index (campaign_id, identifier)
                    // can block it, and campaign_id on the client record is not critical
                    // for conversation continuity. All messages now live on the survivor.
                    $other->delete();
                }
                $mergedCount++;
            }
        }

        $this->line("");
        if ($dryRun) {
            $this->info("DRY RUN: Would merge {$mergedCount} duplicate clients.");
        } else {
            $this->info("Merged {$mergedCount} duplicate clients, moved {$movedMessages} messages.");
        }

        return 0;
    }
}
