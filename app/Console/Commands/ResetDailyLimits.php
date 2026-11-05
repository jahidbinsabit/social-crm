<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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

        Log::info('Daily limits reset', [
            'accounts_reset' => $updated,
            'date'           => $today,
        ]);

        $this->resumePausedCampaigns();
    }

    /**
     * Resume campaigns whose remaining leads were paused at yesterday's
     * quota (#7): paused leads/clients go back to 'pending' and each
     * affected campaign is re-dispatched. pending_count is deliberately
     * untouched — paused leads were never counted out of it.
     */
    private function resumePausedCampaigns(): void
    {
        $campaignIds = collect();

        $pausedLeads = CampaignLead::where('status', 'paused')->get();
        foreach ($pausedLeads->groupBy('campaign_id') as $campaignId => $leads) {
            foreach ($leads as $lead) {
                $lead->update(['status' => 'pending', 'fail_reason' => null]);

                if ($lead->client_id) {
                    Client::where('id', $lead->client_id)
                        ->where('status', 'paused')
                        ->update(['status' => 'pending', 'fail_reason' => null]);
                }
            }
            $campaignIds->push($campaignId);
        }

        // Legacy campaigns without lead rows pause at the client level.
        $pausedClients = Client::where('status', 'paused')
            ->whereNotNull('campaign_id')
            ->get();
        foreach ($pausedClients as $client) {
            $client->update(['status' => 'pending', 'fail_reason' => null]);
            $campaignIds->push($client->campaign_id);
        }

        foreach ($campaignIds->unique() as $campaignId) {
            $campaign = Campaign::find($campaignId);
            if (!$campaign) {
                continue;
            }

            // ProcessCampaignJob refuses 'completed'/'failed' campaigns, so
            // a campaign that was finalised while paused re-opens first.
            if (in_array($campaign->status, ['completed', 'failed'], true)) {
                $campaign->update(['status' => 'pending']);
            }

            ProcessCampaignJob::dispatch($campaign->id)->onQueue('campaign');
            $this->info("Resumed campaign #{$campaign->id} after daily-limit reset.");
        }
    }
}
