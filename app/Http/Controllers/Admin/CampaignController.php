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
            'staff_ids' => 'required|array',
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

            foreach ($normalized as $identifier) {
                // Find existing client or create new client for CRM conversation thread
                $existing = Client::where('platform', $validated['platform'])
                    ->where('identifier', $identifier)
                    ->first();

                if ($existing) {
                    $client = $existing;
                    $client->update([
                        'status' => 'pending',
                        'fail_reason' => null,
                    ]);
                } else {
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

