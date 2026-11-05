<?php

namespace App\Jobs;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\Client;
use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCampaignJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 120; // Just for the dispatch phase, not the sending

    /** How long the uniqueness lock lives if the dispatcher dies mid-run. */
    public int $uniqueFor = 3600;

    public function __construct(public int $campaignId)
    {
    }

    /** One dispatcher per campaign at a time (#8) — no double fan-out. */
    public function uniqueId(): string
    {
        return 'campaign-' . $this->campaignId;
    }

    public function handle(): void
    {
        $campaign = Campaign::find($this->campaignId);
        if (!$campaign) return;

        // A finished campaign is never re-dispatched by accident (#8).
        // Next-day quota resume flips paused campaigns back to 'pending'
        // (ResetDailyLimits) before dispatching, so it passes this guard.
        if (in_array($campaign->status, ['completed', 'failed'], true)) {
            return;
        }

        try {
            $campaign->update(['status' => 'running']);
            $settings = SystemSetting::current();

            // Clamp the delay window (#12): rand() throws ValueError when
            // an admin saves min > max, which used to kill the campaign.
            $minDelay = max(0, (int) $settings->min_delay_seconds);
            $maxDelay = max($minDelay, (int) $settings->max_delay_seconds);

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
                        $minDelay,
                        $maxDelay
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
                    $minDelay,
                    $maxDelay
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
