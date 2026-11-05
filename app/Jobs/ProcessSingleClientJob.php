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

    // tries=3 so release() retries actually happen (#8); timeout must cover
    // the engines' own 120s send timeout, otherwise the worker kills the
    // job mid-send and the retry double-sends.
    public int $tries   = 3;
    public int $timeout = 240;

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
                    'account_id'   => $account->id,
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

        // Atomic claim (#8): exactly one worker may move a pending lead (or
        // legacy client) to 'processing'. A duplicate dispatch, a queue
        // retry after success, or a re-run all see 0 affected rows and stop
        // BEFORE anything is sent.
        if ($lead) {
            $claimed = CampaignLead::where('id', $lead->id)
                ->where('status', 'pending')
                ->update(['status' => 'processing', 'account_id' => $account->id]);

            if ($claimed === 0) {
                return;
            }
        } else {
            $claimed = Client::where('id', $client->id)
                ->where('status', 'pending')
                ->update([
                    'status'              => 'processing',
                    'assigned_account_id' => $account->id,
                ]);

            if ($claimed === 0) {
                return;
            }
        }

        // Per-client send lock — TTL 180s covers the engines' 120s send
        // timeout (#8; it was 60s, so a slow media send outlived its lock
        // and a second worker could send the same message again).
        $lock = Cache::lock("client_send_" . $client->id, 180);
        if (!$lock->get()) {
            // Not a failure: hand the claim back and retry shortly (#8).
            $this->unclaim($lead, $client);
            $this->release(30);
            return;
        }

        $saved = null;
        $reservedAccount = null; // account holding the atomic quota slot (#10)

        try {
            // Atomic quota reservation (#10): the increment itself is the
            // check — two workers can never both take the last slot.
            $reservedAccount = $this->reserveQuota($account, $campaign, $settings);
            if ($reservedAccount) {
                $account = $reservedAccount;
            } else {
                // Quota exhausted everywhere in scope: PAUSE, don't kill
                // (#7). The lead stays sendable; ResetDailyLimits flips it
                // back to 'pending' and re-dispatches tomorrow. Counts are
                // untouched — it is still to-send, not failed.
                $reason = "Daily limit reached for all available accounts — paused until tomorrow";
                $client->update([
                    "status"      => "paused",
                    "fail_reason" => $reason,
                ]);
                if ($lead) {
                    $lead->update([
                        "status"      => "paused",
                        "fail_reason" => $reason,
                    ]);
                }
                return;
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
            // The send is counted by the reservation above (#10) — there is
            // deliberately no second increment here.
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
            // Send failed: give the reserved quota slot back (#10).
            if ($reservedAccount) {
                Account::where('id', $reservedAccount->id)
                    ->where('messages_sent_today', '>', 0)
                    ->decrement('messages_sent_today');
                $reservedAccount = null;
            }

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

        // After processing, check if all campaign leads/clients are done.
        // 'paused' counts as NOT done (#7): the campaign stays 'running'
        // until the paused remainder is resumed and sent.
        $hasPendingLeads = CampaignLead::where("campaign_id", $this->campaignId)
            ->whereIn("status", ["pending", "processing", "paused"])
            ->exists();

        $hasPendingLegacy = false;
        if (!$hasPendingLeads && CampaignLead::where("campaign_id", $this->campaignId)->count() === 0) {
            $hasPendingLegacy = Client::where("campaign_id", $this->campaignId)
                ->whereIn("status", ["pending", "processing", "paused"])
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

    /**
     * Atomically take one daily-quota slot (#10), first on the assigned
     * account, then on alternative in-scope accounts (#9: the fallback
     * honours the campaign's staff_ids restriction, exactly like
     * ProcessCampaignJob's account query). Returns the account that holds
     * the reservation, or null when every in-scope account is full.
     */
    private function reserveQuota(Account $preferred, Campaign $campaign, SystemSetting $settings): ?Account
    {
        $limit = (int) $settings->daily_limit_per_account;

        $tryReserve = function (Account $candidate) use ($limit): bool {
            return Account::where('id', $candidate->id)
                ->where('messages_sent_today', '<', $limit)
                ->increment('messages_sent_today') === 1;
        };

        if ($tryReserve($preferred)) {
            return $preferred;
        }

        $altQuery = Account::where('platform', $campaign->platform)
            ->where('status', 'active')
            ->where('id', '!=', $preferred->id)
            ->where('messages_sent_today', '<', $limit);

        if (!empty($campaign->staff_ids)) {
            $staffIds = is_string($campaign->staff_ids)
                ? json_decode($campaign->staff_ids, true) ?? []
                : $campaign->staff_ids;

            if (!empty($staffIds)) {
                $altQuery->whereHas('staff', fn($q) =>
                    $q->whereIn('users.id', $staffIds)
                );
            }
        }

        foreach ($altQuery->inRandomOrder()->get() as $altAccount) {
            if ($tryReserve($altAccount)) {
                return $altAccount;
            }
        }

        return null;
    }

    /** Return a CAS claim to 'pending' (lock contention is not a failure). */
    private function unclaim(?CampaignLead $lead, Client $client): void
    {
        if ($lead) {
            CampaignLead::where('id', $lead->id)
                ->where('status', 'processing')
                ->update(['status' => 'pending']);
        } else {
            Client::where('id', $client->id)
                ->where('status', 'processing')
                ->update(['status' => 'pending']);
        }
    }
}
