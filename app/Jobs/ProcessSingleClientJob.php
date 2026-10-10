<?php

namespace App\Jobs;

use App\Events\NewMessageReceived;
use App\Models\Account;
use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\Client;
use App\Models\Message;
use App\Models\SystemSetting;
use App\Services\OutboundMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessSingleClientJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 60;

    public function __construct(
        public int $clientId,
        public int $campaignId,
        public int $accountId,
        public ?int $leadId = null
    ) {}

    public function handle(OutboundMessageService $outbound): void
    {
        $campaign = Campaign::find($this->campaignId);
        $account  = Account::find($this->accountId);
        $settings = SystemSetting::current();

        if (!$campaign || !$account) return;

        $lead = $this->leadId ? CampaignLead::find($this->leadId) : null;
        if (!$lead && $this->clientId) {
            $lead = CampaignLead::where('campaign_id', $this->campaignId)
                ->where('client_id', $this->clientId)
                ->first();
        }

        $client = $this->clientId ? Client::find($this->clientId) : null;
        if (!$client && $lead) {
            $client = Client::where('platform', $lead->platform)
                ->where('identifier', $lead->identifier)
                ->first();

            if (!$client) {
                $client = Client::create([
                    'campaign_id'  => $this->campaignId,
                    'platform'     => $lead->platform,
                    'identifier'   => $lead->identifier,
                    'display_name' => $lead->identifier,
                    'status'       => 'pending',
                ]);
            }

            if ($lead && !$lead->client_id) {
                $lead->update(['client_id' => $client->id]);
            }
        }

        if (!$client) return;

        // Atomic lock — prevent duplicate dispatch
        $lock = Cache::lock("client_send_" . $client->id, 60);
        if (!$lock->get()) {
            $this->release(5);
            return;
        }

        $saved = null;

        try {
            // Re-check daily limit
            $account->refresh();
            if ($account->messages_sent_today >= $settings->daily_limit_per_account) {
                // Try finding an alternative active account for this campaign with quota
                $altAccount = Account::where('platform', $campaign->platform)
                    ->where('status', 'active')
                    ->where('id', '!=', $account->id)
                    ->where('messages_sent_today', '<', $settings->daily_limit_per_account)
                    ->inRandomOrder()
                    ->first();

                if ($altAccount) {
                    $account = $altAccount;
                } else {
                    $reason = "Daily limit reached for all available accounts";
                    $client->update([
                        "status"      => "failed",
                        "fail_reason" => $reason,
                    ]);
                    if ($lead) {
                        $lead->update([
                            "status"      => "failed",
                            "fail_reason" => $reason,
                        ]);
                    }
                    $campaign->increment("failed_count");
                    if ($campaign->pending_count > 0) $campaign->decrement("pending_count");
                    return;
                }
            }

            $client->update([
                "status"              => "processing",
                "assigned_account_id" => $account->id,
            ]);
            if ($lead) {
                $lead->update([
                    "status"     => "processing",
                    "account_id" => $account->id,
                ]);
            }

            $outbound->deliver($account, $client, $campaign->message_payload);

            $client->update(["status" => "contacted", "fail_reason" => null]);
            if ($lead) {
                $lead->update([
                    "status"      => "contacted",
                    "account_id"  => $account->id,
                    "fail_reason" => null,
                    "sent_at"     => now(),
                ]);
            }
            $account->increment("messages_sent_today");
            $campaign->increment("contacted_count");
            if ($campaign->pending_count > 0) $campaign->decrement("pending_count");

            $saved = Message::create([
                "platform"     => $campaign->platform,
                "account_id"   => $account->id,
                "client_id"    => $client->id,
                "sender_type"  => "system",
                "message_type" => "text",
                "message_body" => $campaign->message_payload,
            ]);

        } catch (\Throwable $e) {
            $reason = substr($e->getMessage(), 0, 255);
            Log::error("Single client send failed", [
                "client_id"   => $client->id,
                "campaign_id" => $this->campaignId,
                "error"       => $e->getMessage(),
            ]);
            $client->update([
                "status"      => "failed",
                "fail_reason" => $reason,
            ]);
            if ($lead) {
                $lead->update([
                    "status"      => "failed",
                    "account_id"  => $account->id,
                    "fail_reason" => $reason,
                ]);
            }
            $campaign->increment("failed_count");
            if ($campaign->pending_count > 0) $campaign->decrement("pending_count");
        } finally {
            $lock->release();
        }

        // Broadcast AFTER try/catch — Reverb failure must not mark send as failed
        if ($saved) {
            try {
                broadcast(new NewMessageReceived($saved))->toOthers();
            } catch (\Throwable $e) {
                Log::warning("Reverb broadcast failed: " . $e->getMessage());
            }
        }

        // After processing, check if all campaign leads/clients are done
        $hasPendingLeads = CampaignLead::where("campaign_id", $this->campaignId)
            ->whereIn("status", ["pending", "processing"])
            ->exists();

        $hasPendingLegacy = false;
        if (!$hasPendingLeads && CampaignLead::where("campaign_id", $this->campaignId)->count() === 0) {
            $hasPendingLegacy = Client::where("campaign_id", $this->campaignId)
                ->whereIn("status", ["pending", "processing"])
                ->exists();
        }

        if (!$hasPendingLeads && !$hasPendingLegacy) {
            $campaign->refresh();
            if ($campaign->status === "running" || $campaign->status === "pending") {
                $finalStatus = ($campaign->contacted_count === 0 && $campaign->failed_count > 0)
                    ? 'failed'
                    : 'completed';
                $campaign->update(["status" => $finalStatus]);
            }
        }
    }
}
