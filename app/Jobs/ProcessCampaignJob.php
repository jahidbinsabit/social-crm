<?php

namespace App\Jobs;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\Client;
use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 120; // Just for the dispatch phase, not the sending

    public function __construct(public int $campaignId)
    {
    }

    public function handle(): void
    {
        $campaign = Campaign::find($this->campaignId);
        if (!$campaign) return;

        try {
            $campaign->update(['status' => 'running']);
            $settings = SystemSetting::current();

            $accountQuery = Account::where('platform', $campaign->platform)
                ->where('status', 'active')
                ->where('messages_sent_today', '<', $settings->daily_limit_per_account);

            if (!empty($campaign->staff_ids)) {
                $staffIds = is_string($campaign->staff_ids)
                    ? json_decode($campaign->staff_ids, true) ?? []
                    : $campaign->staff_ids;

                $staffFiltered = (clone $accountQuery)->whereHas('staff', fn($q) =>
                    $q->whereIn('users.id', $staffIds)
                )->get()->values();

                $accounts = $staffFiltered;
            } else {
                $accounts = $accountQuery->get()->values();
            }

            if ($accounts->isEmpty()) {
                Log::warning("ProcessCampaignJob: No active accounts found for campaign #{$this->campaignId}");
                $campaign->update(['status' => 'failed']);
                return;
            }

            // Fetch pending leads for this campaign
            $pendingLeads = CampaignLead::where('campaign_id', $this->campaignId)
                ->where('status', 'pending')
                ->get();

            if ($pendingLeads->isNotEmpty()) {
                $accountCount = $accounts->count();
                $accountDelays = [];

                foreach ($pendingLeads as $index => $lead) {
                    $account = $accounts[$index % $accountCount];
                    $accountDelays[$account->id] = ($accountDelays[$account->id] ?? 0) + rand(
                        $settings->min_delay_seconds,
                        $settings->max_delay_seconds
                    );

                    ProcessSingleClientJob::dispatch(
                        $lead->client_id ?? 0,
                        $campaign->id,
                        $account->id,
                        $lead->id
                    )
                    ->onQueue('campaign')
                    ->delay(now()->addSeconds($accountDelays[$account->id]));
                }
                return;
            }

            // Fallback for legacy campaigns without CampaignLead entries
            $pendingClients = Client::where('campaign_id', $this->campaignId)
                ->where('status', 'pending')
                ->get();

            if ($pendingClients->isEmpty()) {
                $campaign->update(['status' => 'completed']);
                return;
            }

            $accountCount = $accounts->count();
            $accountDelays = [];

            foreach ($pendingClients as $index => $client) {
                $account = $accounts[$index % $accountCount];
                $accountDelays[$account->id] = ($accountDelays[$account->id] ?? 0) + rand(
                    $settings->min_delay_seconds,
                    $settings->max_delay_seconds
                );

                ProcessSingleClientJob::dispatch(
                    $client->id,
                    $campaign->id,
                    $account->id
                )
                ->onQueue('campaign')
                ->delay(now()->addSeconds($accountDelays[$account->id]));
            }
        } catch (\Throwable $e) {
            Log::error("ProcessCampaignJob failed for campaign #{$this->campaignId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $campaign->update(['status' => 'failed']);
            throw $e;
        }
    }
}

