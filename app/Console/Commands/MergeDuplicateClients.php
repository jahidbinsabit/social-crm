<?php

namespace App\Console\Commands;

use App\Models\CampaignLead;
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

        // The whole merge runs in ONE transaction (#35): a crash halfway
        // used to leave messages moved but the duplicate alive (or leads
        // orphaned), which then resurrected the duplicate on the next send.
        DB::transaction(function () use ($duplicates, $dryRun, &$mergedCount, &$movedMessages) {
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
                // #13 guard: since conversations are per-account, records
                // owned by DIFFERENT accounts are not duplicates, even with
                // the same identifier — never merge across accounts.
                $survivorAccount = $survivor->account_id ?? $survivor->assigned_account_id;
                $otherAccount = $other->account_id ?? $other->assigned_account_id;
                if ($survivorAccount && $otherAccount && (int) $survivorAccount !== (int) $otherAccount) {
                    $this->warn("  Skip:     client #{$other->id} belongs to account {$otherAccount}, survivor to {$survivorAccount} — not a duplicate");
                    continue;
                }

                $msgCount = Message::where('client_id', $other->id)->count();
                $this->line("  Merge:    client #{$other->id} (campaign {$other->campaign_id}) — {$msgCount} messages");

                if (!$dryRun) {
                    // Move all messages to survivor
                    $moved = Message::where('client_id', $other->id)
                        ->update(['client_id' => $survivor->id]);
                    $movedMessages += $moved;

                    // Re-point campaign leads to the survivor (#35) instead
                    // of nulling them via the FK — orphaned leads used to
                    // recreate the duplicate client on their next send.
                    // Leads that would collide with a survivor lead in the
                    // same campaign (unique campaign_id+identifier) are
                    // dropped; the survivor's lead already covers it.
                    $survivorCampaigns = CampaignLead::where('client_id', $survivor->id)
                        ->pluck('campaign_id');
                    CampaignLead::where('client_id', $other->id)
                        ->whereIn('campaign_id', $survivorCampaigns)
                        ->delete();
                    CampaignLead::where('client_id', $other->id)
                        ->update(['client_id' => $survivor->id]);

                    // Delete the duplicate inside the same transaction —
                    // messages and leads already live on the survivor.
                    $other->delete();
                }
                $mergedCount++;
            }
        }
        });

        $this->line("");
        if ($dryRun) {
            $this->info("DRY RUN: Would merge {$mergedCount} duplicate clients.");
        } else {
            $this->info("Merged {$mergedCount} duplicate clients, moved {$movedMessages} messages.");
        }

        return 0;
    }
}
