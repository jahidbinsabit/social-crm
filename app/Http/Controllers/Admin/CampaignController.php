<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\Client;
use App\Models\Message;
use App\Jobs\ProcessCampaignJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::with('creator')->latest()->get();

        return view('admin.campaigns', compact('campaigns'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'platform' => 'required|in:telegram,whatsapp',
            'message_payload' => 'required|string',
            'client_list' => 'required|string',
            'staff_ids' => 'required|array|min:1',
            // Every assignee must be a real staff user (#11) — junk or admin
            // IDs previously produced campaigns that silently failed.
            'staff_ids.*' => ['integer', Rule::exists('users', 'id')->where('role', 'staff')],
        ]);

        $rawIdentifiers = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $validated['client_list'])));

        // Normalize: strip leading @ for telegram so @user and user are the same client
        $normalized = [];
        foreach ($rawIdentifiers as $identifier) {
            $norm = $identifier;
            if ($validated['platform'] === 'telegram' && str_starts_with($norm, '@')) {
                $norm = substr($norm, 1);
            }
            if (!empty($norm)) {
                $normalized[strtolower($norm)] = $norm;
            }
        }

        $totalLeads = count($normalized);

        if ($totalLeads === 0) {
            return back()->withInput()->with('error', 'No valid client identifiers or phone numbers found in client list.');
        }

        // Hard cap (#11): an unbounded list must not become one giant
        // single-transaction mass create.
        if ($totalLeads > 10000) {
            return back()->withInput()->with('error', 'Client list is too large: maximum 10,000 identifiers per campaign.');
        }

        $campaign = DB::transaction(function () use ($validated, $normalized, $totalLeads, $request) {
            $campaign = Campaign::create([
                'name' => $validated['name'],
                'platform' => $validated['platform'],
                'message_payload' => $validated['message_payload'],
                'status' => 'pending',
                'created_by' => $request->user()->id,
                'staff_ids' => $validated['staff_ids'],
                'total_leads' => $totalLeads,
                'contacted_count' => 0,
                'failed_count' => 0,
                'pending_count' => $totalLeads,
            ]);

            // Chunked writes (#11): 500 rows at a time inside the one
            // transaction instead of one unbounded mass create.
            foreach (array_chunk($normalized, 500, true) as $chunk) {
                foreach ($chunk as $identifier) {
                    // Reuse an existing client row for the conversation
                    // thread if there is one. Its status is NOT touched
                    // (#11): the row may belong to another campaign or an
                    // inbound thread — resetting it to 'pending' here used
                    // to corrupt that campaign's state. Per-campaign state
                    // lives on the lead record created below.
                    $client = Client::where('platform', $validated['platform'])
                        ->where('identifier', $identifier)
                        ->first();

                    if (!$client) {
                        $client = Client::create([
                            'campaign_id'         => $campaign->id,
                            'platform'            => $validated['platform'],
                            'identifier'          => $identifier,
                            'display_name'        => $identifier,
                            'status'              => 'pending',
                            'fail_reason'         => null,
                            'assigned_account_id' => null,
                        ]);
                    }

                    // Create immutable campaign lead record
                    CampaignLead::create([
                        'campaign_id' => $campaign->id,
                        'client_id'   => $client->id,
                        'identifier'  => $identifier,
                        'platform'    => $validated['platform'],
                        'status'      => 'pending',
                    ]);
                }
            }

            return $campaign;
        });

        ProcessCampaignJob::dispatch($campaign->id)->onQueue('campaign');

        return Redirect::route('admin.campaigns')->with('success', 'Campaign created and queued');
    }

    public function details(int $id)
    {
        $campaign = Campaign::findOrFail($id);

        $leads = $campaign->leads()->get(['identifier', 'status', 'fail_reason', 'sent_at']);

        if ($leads->isEmpty()) {
            $leads = $campaign->clients()->get(['identifier', 'status', 'fail_reason']);
        }

        return response()->json([
            'clients' => $leads,
        ]);
    }
}

