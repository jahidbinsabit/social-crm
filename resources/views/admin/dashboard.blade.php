@extends('layouts.admin')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('content')

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px;">

    @php
    $cards = [
        ['label' => 'Active Accounts', 'value' => $stats['active_accounts'] . ' / ' . $stats['total_accounts'], 'color' => 'var(--success)'],
        ['label' => 'Active Staff',    'value' => $stats['active_staff'] . ' / ' . $stats['total_staff'],       'color' => 'var(--accent)'],
        ['label' => 'Running Campaigns','value' => $stats['running_campaigns'],                                  'color' => 'var(--warning)'],
        ['label' => 'Messages Today',  'value' => $stats['messages_today'],                                      'color' => 'var(--text-primary)'],
        ['label' => 'Unread Messages', 'value' => $stats['unread_messages'],                                     'color' => 'var(--danger)'],
        ['label' => 'Clients Contacted','value' => $stats['clients_contacted'],                                  'color' => 'var(--success)'],
    ];
    @endphp

    @foreach($cards as $card)
    <div style="background-color: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 20px 24px;">
        <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">{{ $card['label'] }}</p>
        <p style="font-size: 28px; font-weight: 700; color: {{ $card['color'] }};">{{ $card['value'] }}</p>
    </div>
    @endforeach
</div>

<div style="background-color: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; overflow: hidden;">
    <div style="padding: 18px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 16px; font-weight: 700;">Recent Incoming Messages</h3>
        <a href="{{ route('admin.accounts') }}" style="font-size: 13px; color: var(--accent); text-decoration: none;">View All →</a>
    </div>
    @forelse($recentMessages as $msg)
    <div style="padding: 14px 24px; border-bottom: 1px solid var(--border); display: flex; gap: 16px; align-items: flex-start;">
        <span style="font-size: 20px;">{{ $msg->platform === 'telegram' ? '✈' : '💬' }}</span>
        <div style="flex: 1; min-width: 0;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                <span style="font-size: 13px; font-weight: 600;">{{ $msg->client?->identifier ?? 'Unknown' }}</span>
                <span style="font-size: 11px; color: var(--text-secondary);">{{ $msg->created_at->diffForHumans() }}</span>
            </div>
            <p style="font-size: 13px; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $msg->message_body }}</p>
            <span style="font-size: 11px; color: var(--text-secondary);">via {{ $msg->account?->account_name ?? 'Unknown Account' }}</span>
        </div>
        @if(!$msg->is_read)
        <span style="width: 8px; height: 8px; border-radius: 50%; background-color: var(--danger); flex-shrink: 0; margin-top: 4px;"></span>
        @endif
    </div>
    @empty
    <div style="padding: 48px 24px; text-align: center; color: var(--text-secondary);">
        <div style="font-size: 40px; margin-bottom: 10px;">📭</div>
        <p style="font-size: 14px;">No messages yet</p>
    </div>
    @endforelse
</div>

@endsection

@push('scripts')
<script>
(function() {
    let attempts = 0;
    function tryInit() {
        if (typeof window.Echo === 'undefined' || !window.Echo.channel) {
            if (++attempts <= 50) { setTimeout(tryInit, 100); return; }
            console.error('[Admin Realtime] Echo not loaded after 5s');
            return;
        }

        console.log('[Admin Realtime] Echo ready (attempt ' + attempts + ')');

        // Admin listens on the admin-events channel (broadcast by NewMessageReceived)
        const ch = window.Echo.channel('admin-events');

        ch.subscribed(() => {
            console.log('[Admin Realtime] ✅ Subscribed to admin-events');
        });

        ch.listen('.NewMessageReceived', function(e) {
        const msg = e.message;
        console.log('[Admin Realtime] 📩 NewMessageReceived', msg);
        if (!msg) return;

        // Find the card body that holds message rows (the grid card with overflow)
        const container = document.querySelector('div[style*="overflow: hidden"]');
        if (!container) {
            console.warn('[Admin Realtime] container not found');
            return;
        }

        // Build the new message row
        const platformIcon = msg.platform === 'telegram' ? '✈' : '💬';
        const identifier = msg.client?.identifier || 'Unknown';
        const accountName = msg.account?.account_name || 'Unknown Account';
        const body = (msg.message_body || '').substring(0, 100);
        const timeAgo = 'just now';

        const row = document.createElement('div');
        row.style.cssText = 'padding:14px 24px;border-bottom:1px solid var(--border);display:flex;gap:16px;align-items:flex-start;animation:msg-pop 0.3s ease;';
        row.innerHTML = `
            <span style="font-size:20px;">${platformIcon}</span>
            <div style="flex:1;min-width:0;">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span style="font-size:13px;font-weight:600;">${identifier}</span>
                    <span style="font-size:11px;color:var(--text-secondary);">${timeAgo}</span>
                </div>
                <p style="font-size:13px;color:var(--text-secondary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${body}</p>
                <span style="font-size:11px;color:var(--text-secondary);">via ${accountName}</span>
            </div>
            <span style="width:8px;height:8px;border-radius:50%;background-color:var(--danger);flex-shrink:0;margin-top:4px;"></span>
        `;

        // Prepend to list (after the header)
        const header = container.children[0];
        const second = container.children[1];
        if (second && !second.querySelector('div[style*="font-size: 40px"]')) {
            container.insertBefore(row, second);
        } else {
            // List was empty or has the "no messages" placeholder — replace it
            const empty = container.querySelector('div[style*="text-align: center"]');
            if (empty) {
                empty.replaceWith(row);
            } else {
                header.insertAdjacentElement('afterend', row);
            }
        }
    });

    ch.error((err) => console.error('[Admin Realtime] ❌ channel error', err));
    } // end tryInit
    tryInit();
})();
</script>
@endpush
