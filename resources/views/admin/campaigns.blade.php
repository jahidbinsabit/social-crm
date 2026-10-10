@extends('layouts.admin')

@section('title', 'Campaign')
@section('page-title', 'Campaign')

@section('content')
    @if(session('success'))
        <div style="background-color: rgba(34, 197, 94, 0.1); border: 1px solid var(--success); color: #86efac; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px;">
            {{ session('success') }}
        </div>
    @endif

    <!-- Create Campaign Form -->
    <div style="background-color: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 24px;">
        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">Create Campaign</h3>

        <form method="POST" action="{{ route('admin.campaigns.store') }}" class="campaign-form">
            @csrf
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Campaign Name</label>
                    <input
                        type="text"
                        name="name"
                        required
                        style="width: 100%; padding: 10px 14px; background-color: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none;"
                    >
                </div>

                <div>
                    <label style="display: block; font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Platform</label>
                    <select
                        name="platform"
                        required
                        style="width: 100%; padding: 10px 14px; background-color: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none;"
                    >
                        <option value="telegram">Telegram</option>
                        <option value="whatsapp">WhatsApp</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Message</label>
                <textarea
                    name="message_payload"
                    rows="4"
                    required
                    style="width: 100%; padding: 10px 14px; background-color: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none; resize: vertical;"
                ></textarea>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Client List (one per line)</label>
                <textarea
                    name="client_list"
                    rows="6"
                    required
                    placeholder="@username1&#10;+1234567890&#10;client_id_123"
                    style="width: 100%; padding: 10px 14px; background-color: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none; resize: vertical;"
                ></textarea>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Staff Accounts</label>
                <div style="display: flex; flex-wrap: wrap; gap: 12px; padding: 12px; background-color: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px;">
                    @php
                        $staffUsers = \App\Models\User::where('role', 'staff')->where('status', 'active')->get();
                    @endphp

                    @if($staffUsers->isEmpty())
                        <span style="color: var(--text-secondary); font-size: 13px;">No active staff members found.</span>
                    @endif

                    @foreach($staffUsers as $staffMember)
                        <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 14px; color: var(--text-primary); cursor: pointer;">
                            <input type="checkbox" name="staff_ids[]" value="{{ $staffMember->id }}" checked style="accent-color: var(--accent);">
                            {{ $staffMember->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <button type="submit" style="padding: 10px 20px; background-color: var(--accent); color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer;">
                Launch Campaign
            </button>
        </form>
    </div>

    <!-- Campaign History -->
    <div style="background-color: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; overflow: hidden;">
        <div style="padding: 20px 24px; border-bottom: 1px solid var(--border);">
            <h3 style="font-size: 18px; font-weight: 700;">Campaign History</h3>
            <p style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">{{ $campaigns->count() }} total</p>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="background-color: var(--bg-primary);">
                        <th style="text-align: left; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">ID</th>
                        <th style="text-align: left; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">Name</th>
                        <th style="text-align: left; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">Platform</th>
                        <th style="text-align: left; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">Status</th>
                        <th style="text-align: left; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">Total</th>
                        <th style="text-align: left; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">Contacted</th>
                        <th style="text-align: left; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">Failed</th>
                        <th style="text-align: left; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">Created</th>
                        <th style="text-align: right; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($campaigns as $campaign)
                        @php
                            $total = $campaign->total_leads > 0 ? $campaign->total_leads : ($campaign->leads()->count() ?: $campaign->clients()->count());
                            $contacted = $campaign->contacted_count > 0 ? $campaign->contacted_count : ($campaign->leads()->where('status', 'contacted')->count() ?: $campaign->clients()->where('status', 'contacted')->count());
                            $failed = $campaign->failed_count > 0 ? $campaign->failed_count : ($campaign->leads()->where('status', 'failed')->count() ?: $campaign->clients()->where('status', 'failed')->count());

                            $statusColor = match($campaign->status) {
                                'pending' => 'var(--warning)',
                                'running' => 'var(--accent)',
                                'completed' => 'var(--success)',
                                'failed' => 'var(--danger)',
                                default => 'var(--text-secondary)',
                            };
                        @endphp
                        <tr style="border-top: 1px solid var(--border);">
                            <td style="padding: 14px 24px; color: var(--text-secondary);">{{ $campaign->id }}</td>
                            <td style="padding: 14px 24px; font-weight: 600;">{{ $campaign->name }}</td>
                            <td style="padding: 14px 24px; text-transform: capitalize;">{{ $campaign->platform }}</td>
                            <td style="padding: 14px 24px;">
                                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; color: {{ $statusColor }}; border: 1px solid {{ $statusColor }}; background-color: rgba(255,255,255,0.02);">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background-color: {{ $statusColor }};"></span>
                                    {{ $campaign->status }}
                                </span>
                            </td>
                            <td style="padding: 14px 24px; font-weight: 600;">{{ $total }}</td>
                            <td style="padding: 14px 24px; color: var(--success); font-weight: 600;">{{ $contacted }}</td>
                            <td style="padding: 14px 24px; color: var(--danger); font-weight: 600;">{{ $failed }}</td>
                            <td style="padding: 14px 24px; color: var(--text-secondary);">{{ $campaign->created_at->format('Y-m-d H:i') }}</td>
                            <td style="padding: 14px 24px; text-align: right;">
                                <button
                                    type="button"
                                    onclick="viewDetails({{ $campaign->id }})"
                                    style="padding: 6px 12px; background-color: transparent; color: var(--accent); border: 1px solid var(--accent); border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;"
                                >
                                    View Details
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="padding: 40px 24px; text-align: center; color: var(--text-secondary);">
                                No campaigns yet. Create your first campaign above.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Details Modal -->
    <div id="details-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
        <div style="background-color: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; max-width: 800px; width: 100%; max-height: 80vh; overflow: hidden; display: flex; flex-direction: column;">
            <div style="padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 18px; font-weight: 700;">Campaign Details</h3>
                <button onclick="closeModal()" style="background: transparent; border: none; color: var(--text-secondary); font-size: 20px; cursor: pointer;">&times;</button>
            </div>
            <div id="modal-body" style="padding: 24px; overflow-y: auto; flex: 1;"></div>
        </div>
    </div>

    <script>
        function viewDetails(campaignId) {
            fetch('/admin/campaigns/' + campaignId + '/details')
                .then(r => r.json())
                .then(data => {
                    const modal = document.getElementById('details-modal');
                    const body = document.getElementById('modal-body');

                    let html = '<div style="overflow-x: auto;"><table style="width: 100%; border-collapse: collapse; font-size: 14px;">';
                    html += '<thead><tr style="background-color: var(--bg-primary);"><th style="text-align: left; padding: 10px 16px; color: var(--text-secondary); font-weight: 600;">Identifier</th><th style="text-align: left; padding: 10px 16px; color: var(--text-secondary); font-weight: 600;">Status</th><th style="text-align: left; padding: 10px 16px; color: var(--text-secondary); font-weight: 600;">Info / Error</th></tr></thead><tbody>';

                    if (!data.clients || data.clients.length === 0) {
                        html += '<tr><td colspan="3" style="padding: 24px; text-align: center; color: var(--text-secondary);">No lead details available for this campaign.</td></tr>';
                    } else {
                        data.clients.forEach(client => {
                            const statusColor = client.status === 'contacted' ? 'var(--success)' : (client.status === 'failed' ? 'var(--danger)' : 'var(--text-secondary)');
                            const details = client.fail_reason ? client.fail_reason : (client.sent_at ? ('Sent at ' + new Date(client.sent_at).toLocaleTimeString()) : '-');
                            html += '<tr style="border-top: 1px solid var(--border);"><td style="padding: 10px 16px; font-weight: 500;">' + client.identifier + '</td><td style="padding: 10px 16px; color: ' + statusColor + '; text-transform: capitalize; font-weight: 600;">' + client.status + '</td><td style="padding: 10px 16px; color: var(--text-secondary); font-size: 12px; max-width: 300px; word-break: break-word;">' + details + '</td></tr>';
                        });
                    }

                    html += '</tbody></table></div>';
                    body.innerHTML = html;
                    modal.style.display = 'flex';
                });
        }

        function closeModal() {
            document.getElementById('details-modal').style.display = 'none';
        }

        document.getElementById('details-modal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
@endsection
