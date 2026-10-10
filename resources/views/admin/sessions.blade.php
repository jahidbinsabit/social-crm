@extends('layouts.admin')

@section('title', 'Account Sessions')
@section('page-title', 'Account Sessions')

@section('content')
    @if(!empty($engineErrors))
        <div style="background-color: rgba(245, 158, 11, 0.1); border: 1px solid var(--warning); color: #fcd34d; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px;">
            @foreach($engineErrors as $engineError)
                <div>{{ $engineError }}</div>
            @endforeach
            <div style="margin-top: 6px; font-size: 13px; opacity: 0.85;">Start it with <code>pm2 restart all</code>, then reload this page.</div>
        </div>
    @endif

    @if(session('success'))
        <div style="background-color: rgba(34, 197, 94, 0.1); border: 1px solid var(--success); color: #86efac; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px;">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: #fecaca; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px;">
            {{ session('error') }}
        </div>
    @endif

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px;">

        <!-- WhatsApp Section -->
        <div style="background-color: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                <div style="font-size: 28px;">💬</div>
                <div>
                    <h3 style="font-size: 18px; font-weight: 700;">WhatsApp</h3>
                    <p style="font-size: 13px; color: var(--text-secondary);">Connect via QR or pairing code</p>
                </div>
            </div>

            <!-- Tab switcher -->
            <div style="display: flex; gap: 8px; margin-bottom: 16px;">
                <button type="button" id="wa-tab-qr" onclick="showWaTab('qr')" style="flex: 1; padding: 8px; background: #22c55e; color: #000; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 13px;">QR Code</button>
                <button type="button" id="wa-tab-pairing" onclick="showWaTab('pairing')" style="flex: 1; padding: 8px; background: transparent; color: var(--text-secondary); border: 1px solid var(--border); border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 13px;">Pairing Code</button>
            </div>

            <!-- QR form -->
            <div id="wa-qr-panel">
                <form method="POST" action="{{ route('admin.sessions.wa.start') }}" style="margin-bottom: 16px;">
                    @csrf
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Phone Number</label>
                        <input type="text" name="phone_number" required placeholder="+1234567890" style="width: 100%; padding: 10px 14px; background-color: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none;">
                    </div>
                    <button type="submit" style="width: 100%; padding: 10px; background-color: #22c55e; color: #000; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer;">Start Session</button>
                </form>

                <div id="wa-qr-area" style="text-align: center; display: none;">
                    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">Scan QR with WhatsApp</p>
                    <img id="qr-img" src="" alt="QR Code" style="max-width: 240px; border-radius: 12px; border: 1px solid var(--border);">
                    <p id="qr-status" style="font-size: 12px; color: var(--text-secondary); margin-top: 8px;">Waiting for QR...</p>
                </div>
            </div>

            <!-- Pairing form -->
            <div id="wa-pairing-panel" style="display: none;">
                <form method="POST" action="{{ route('admin.sessions.wa.pair') }}" style="margin-bottom: 16px;">
                    @csrf
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Phone Number</label>
                        <input type="text" name="phone_number" required placeholder="+1234567890" style="width: 100%; padding: 10px 14px; background-color: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none;">
                    </div>
                    <button type="submit" style="width: 100%; padding: 10px; background-color: #3b82f6; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer;">Send Pairing Code</button>
                </form>
                <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 12px;">After submitting, open <strong>WhatsApp → Settings → Linked Devices → Link with phone number</strong> and enter the code below.</p>
                <div id="wa-pairing-area" style="text-align: center; display: none;">
                    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 8px;">Your pairing code</p>
                    <div id="pairing-code" style="font-size: 32px; font-weight: 700; letter-spacing: 6px; color: #3b82f6; background: var(--bg-primary); padding: 16px; border-radius: 12px; border: 1px solid var(--border);">------</div>
                    <p id="pairing-status" style="font-size: 12px; color: var(--text-secondary); margin-top: 10px;">Waiting for code...</p>
                </div>
            </div>
        </div>

        <!-- Telegram Section -->
        <div style="background-color: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                <div style="font-size: 28px;">✈</div>
                <div>
                    <h3 style="font-size: 18px; font-weight: 700;">Telegram</h3>
                    <p style="font-size: 13px; color: var(--text-secondary);">Connect via OTP</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.sessions.tg.otp') }}" style="margin-bottom: 20px;">
                @csrf
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Phone Number</label>
                    <input
                        type="text"
                        name="phone_number"
                        required
                        placeholder="+1234567890"
                        style="width: 100%; padding: 10px 14px; background-color: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none;"
                    >
                </div>
                <button type="submit" style="width: 100%; padding: 10px; background-color: #3b82f6; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer;">
                    Send OTP
                </button>
            </form>

            <form method="POST" action="{{ route('admin.sessions.tg.verify') }}">
                @csrf
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">OTP Code</label>
                    <input
                        type="text"
                        name="code"
                        required
                        placeholder="123456"
                        style="width: 100%; padding: 10px 14px; background-color: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none;"
                    >
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">2FA Password (optional)</label>
                    <input
                        type="text"
                        name="phone2fa"
                        placeholder="Only if account has 2FA enabled"
                        style="width: 100%; padding: 10px 14px; background-color: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none;"
                    >
                </div>
                <button type="submit" style="width: 100%; padding: 10px; background-color: #3b82f6; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer;">
                    Verify & Connect
                </button>
            </form>
        </div>

    </div>

    <!-- Account List -->
    <div style="background-color: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; overflow: hidden;">
        @php
            $waAccounts = $accounts->where('platform', 'whatsapp');
            $tgAccounts = $accounts->where('platform', 'telegram');
        @endphp

        <div style="padding: 20px 24px; border-bottom: 1px solid var(--border);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; flex-wrap: wrap; gap: 12px;">
                <h3 style="font-size: 18px; font-weight: 700;">Accounts</h3>
                <form method="POST" action="{{ route('admin.sessions.sync') }}" onsubmit="return confirm('Sync session statuses from both engines? This may mark disconnected accounts as offline.')">
                    @csrf
                    <button type="submit" style="padding: 8px 16px; background-color: var(--platform-color, #3b82f6); color: #fff; border: none; border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer;">
                        🔄 Force Sync Status
                    </button>
                </form>
            </div>

            <!-- Platform list switcher -->
            <div style="display: flex; gap: 8px;">
                <button type="button" id="acct-tab-whatsapp" onclick="showAcctList('whatsapp')"
                    style="padding: 8px 16px; background: #22c55e; color: #000; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 13px;">
                    💬 WhatsApp ({{ $waAccounts->count() }})
                </button>
                <button type="button" id="acct-tab-telegram" onclick="showAcctList('telegram')"
                    style="padding: 8px 16px; background: transparent; color: var(--text-secondary); border: 1px solid var(--border); border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 13px;">
                    ✈ Telegram ({{ $tgAccounts->count() }})
                </button>
            </div>
        </div>

        @foreach(['whatsapp' => $waAccounts, 'telegram' => $tgAccounts] as $platform => $platformAccounts)
        <div id="acct-list-{{ $platform }}" style="overflow-x: auto; {{ $platform === 'telegram' ? 'display: none;' : '' }}">
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="background-color: var(--bg-primary);">
                        <th style="text-align: left; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">ID</th>
                        <th style="text-align: left; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">Platform</th>
                        <th style="text-align: left; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">Phone</th>
                        <th style="text-align: left; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">Account Name</th>
                        <th style="text-align: left; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">DB Status</th>
                        <th style="text-align: left; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">Engine Status</th>
                        <th style="text-align: right; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($platformAccounts as $account)
                        @php
                            $engineStatuses = $platform === 'whatsapp' ? ($waEngineStatuses ?? []) : ($tgEngineStatuses ?? []);
                            $engineStatus = $engineStatuses[(string) $account->id] ?? null;
                            $statusColor = match($account->status) {
                                'active' => 'var(--success)',
                                'connecting' => 'var(--warning)',
                                default => 'var(--danger)',
                            };
                            $engineColor = match($engineStatus) {
                                'active', 'connected', 'open' => 'var(--success)',
                                'connecting', 'qr' => 'var(--warning)',
                                default => 'var(--danger)',
                            };
                            $mismatch = $engineStatus !== null && $engineStatus !== 'unknown'
                                && $account->status !== match($engineStatus) {
                                    'active', 'connected', 'open' => 'active',
                                    'connecting', 'qr' => 'connecting',
                                    default => 'disconnected',
                                };
                        @endphp
                        <tr style="border-top: 1px solid var(--border); {{ $mismatch ? 'background-color: rgba(239, 68, 68, 0.08);' : '' }}">
                            <td style="padding: 14px 24px; color: var(--text-secondary);">{{ $account->id }}</td>
                            <td style="padding: 14px 24px; text-transform: capitalize;">{{ $account->platform }}</td>
                            <td style="padding: 14px 24px;">{{ $account->phone_number }}</td>
                            <td style="padding: 14px 24px;">{{ $account->account_name }}</td>
                            <td style="padding: 14px 24px;">
                                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; color: {{ $statusColor }}; border: 1px solid {{ $statusColor }}; background-color: rgba(255,255,255,0.02);">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background-color: {{ $statusColor }};"></span>
                                    {{ $account->status }}
                                </span>
                            </td>
                            <td style="padding: 14px 24px;">
                                @if($engineStatus && $engineStatus !== 'unknown')
                                    <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; color: {{ $engineColor }}; border: 1px solid {{ $engineColor }}; background-color: rgba(255,255,255,0.02);">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background-color: {{ $engineColor }};"></span>
                                        {{ $engineStatus }}
                                    </span>
                                    @if($mismatch)
                                        <div style="font-size: 11px; color: var(--danger); margin-top: 4px;">⚠ mismatch</div>
                                    @endif
                                @else
                                    <span style="font-size: 12px; color: var(--text-secondary);">—</span>
                                @endif
                            </td>
                            <td style="padding: 14px 24px; text-align: right;">
                                <form method="POST" action="{{ route('admin.sessions.disconnect', $account) }}" style="display: inline;" onsubmit="return confirm('Disconnect this account?')">
                                    @csrf
                                    <button type="submit" style="padding: 6px 12px; background-color: transparent; color: var(--warning); border: 1px solid var(--warning); border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">
                                        Disconnect
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.sessions.delete', $account) }}" style="display: inline; margin-left: 8px;" onsubmit="return confirm('Delete this account permanently?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="padding: 6px 12px; background-color: transparent; color: var(--danger); border: 1px solid var(--danger); border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>

                        <!-- Pairing code display row for WA accounts -->
                        @if($platform === 'whatsapp')
                        <tr id="pairing-code-row-{{ $account->id }}" style="display: none; background-color: rgba(37, 211, 102, 0.05);">
                            <td colspan="7" style="padding: 10px 24px;">
                                <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                                    <div style="font-size: 13px; color: var(--text-secondary); font-weight: 600;">Pairing Code:</div>
                                    <div id="pairing-code-display-{{ $account->id }}" style="display: none; background: #1a3a2a; border: 2px solid #25D366; border-radius: 8px; padding: 12px 20px; font-size: 28px; font-weight: 700; letter-spacing: 4px; color: #25D366; font-family: monospace;" title="Enter this code in WhatsApp → Settings → Linked Devices → Link with Phone Number"></div>
                                    <span style="font-size: 12px; color: var(--text-secondary);">Enter this code in WhatsApp → Settings → Linked Devices → Link with phone number</span>
                                </div>
                            </td>
                        </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" style="padding: 40px 24px; text-align: center; color: var(--text-secondary);">
                                No {{ ucfirst($platform) }} accounts yet. Add one using the forms above.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endforeach
    </div>

    <script>
        // Listen for pairing codes from WA engine (Echo is already initialized in layout)
        @auth
        (function initSessionsEcho() {
            var attempts = 0;
            function tryInit() {
                if (window.Echo && window.Echo.channel) {
                    window.Echo.channel('admin-events').listen('PairingCodeReceived', function(e) {
                        var codeBox = document.getElementById('pairing-code-display-' + e.accountId);
                        if (codeBox) {
                            codeBox.textContent = e.code;
                            codeBox.style.display = 'block';
                            codeBox.style.background = '#1a3a2a';
                            codeBox.style.border = '2px solid #25D366';
                            codeBox.style.borderRadius = '8px';
                            codeBox.style.padding = '12px 20px';
                            codeBox.style.fontSize = '28px';
                            codeBox.style.fontWeight = '700';
                            codeBox.style.letterSpacing = '4px';
                            codeBox.style.color = '#25D366';
                            codeBox.style.fontFamily = 'monospace';
                            codeBox.title = 'Enter this code in WhatsApp → Settings → Linked Devices → Link with Phone Number';
                        }
                    });
                } else if (++attempts <= 50) {
                    setTimeout(tryInit, 100);
                }
            }
            tryInit();
        })();
        @endauth

        let currentQrAccountId = null;
        let qrInterval = null;
        let pairingInterval = null;
        let currentPairingId = null;

        function showAcctList(platform) {
            const isWa = platform === 'whatsapp';
            document.getElementById('acct-list-whatsapp').style.display = isWa ? '' : 'none';
            document.getElementById('acct-list-telegram').style.display = isWa ? 'none' : '';

            const waBtn = document.getElementById('acct-tab-whatsapp');
            const tgBtn = document.getElementById('acct-tab-telegram');
            const active = { background: isWa ? '#22c55e' : '#3b82f6', color: isWa ? '#000' : '#fff', border: 'none' };
            const idle = { background: 'transparent', color: 'var(--text-secondary)', border: '1px solid var(--border)' };

            Object.assign((isWa ? waBtn : tgBtn).style, active);
            Object.assign((isWa ? tgBtn : waBtn).style, idle);

            localStorage.setItem('admin_acct_list', platform);
        }

        // Restore the last-viewed list across reloads
        document.addEventListener('DOMContentLoaded', function () {
            const saved = localStorage.getItem('admin_acct_list');
            if (saved === 'telegram') showAcctList('telegram');
        });

        function showWaTab(tab) {
            const qrPanel = document.getElementById('wa-qr-panel');
            const pairingPanel = document.getElementById('wa-pairing-panel');
            const qrTab = document.getElementById('wa-tab-qr');
            const pairingTab = document.getElementById('wa-tab-pairing');

            if (tab === 'qr') {
                qrPanel.style.display = 'block';
                pairingPanel.style.display = 'none';
                qrTab.style.background = '#22c55e'; qrTab.style.color = '#000';
                pairingTab.style.background = 'transparent'; pairingTab.style.color = 'var(--text-secondary)';
            } else {
                qrPanel.style.display = 'none';
                pairingPanel.style.display = 'block';
                pairingTab.style.background = '#3b82f6'; pairingTab.style.color = '#fff';
                qrTab.style.background = 'transparent'; qrTab.style.color = 'var(--text-secondary)';
            }

            clearInterval(qrInterval);
            clearInterval(pairingInterval);
        }

        function pollQr(accountId) {
            console.log('[QR] Starting poll for account:', accountId);
            currentQrAccountId = accountId;
            const qrArea = document.getElementById('wa-qr-area');
            const qrImg = document.getElementById('qr-img');
            const qrStatus = document.getElementById('qr-status');

            if (!qrArea || !qrImg || !qrStatus) {
                console.error('[QR] Missing DOM elements!');
                return;
            }

            qrArea.style.display = 'block';
            qrImg.src = '';
            qrStatus.textContent = 'Starting QR generation...';
            qrStatus.style.color = 'var(--text-secondary)';

            let attempts = 0;
            const maxAttempts = 90; // 3 minutes max (90 * 2s)

            qrInterval = setInterval(() => {
                attempts++;
                if (attempts > maxAttempts) {
                    qrStatus.textContent = 'QR timeout. Please try again.';
                    qrStatus.style.color = 'var(--danger)';
                    clearInterval(qrInterval);
                    return;
                }

                // Fetch the QR image as a blob so we can verify content-type
                const url = '/admin/sessions/wa/qr/' + accountId + '?t=' + Date.now();
                console.log('[QR] Fetching:', url);
                fetch(url, { credentials: 'same-origin', cache: 'no-store' })
                    .then(resp => {
                        const ct = resp.headers.get('content-type') || '';
                        console.log('[QR] Response:', resp.status, ct, resp.headers.get('content-length') + ' bytes');
                        if (!resp.ok || !ct.includes('image')) {
                            throw new Error('Not an image: ' + resp.status + ' ' + ct);
                        }
                        return resp.blob();
                    })
                    .then(blob => {
                        console.log('[QR] Blob size:', blob.size, 'type:', blob.type);
                        // Check if the blob is a real QR (not a 1x1 placeholder ~47 bytes)
                        if (blob.size < 100) {
                            qrStatus.textContent = 'Generating QR... (' + attempts + '/' + maxAttempts + ')';
                            return;
                        }
                        // Real QR — display it
                        const objectUrl = URL.createObjectURL(blob);
                        qrImg.onload = () => {
                            console.log('[QR] Image loaded successfully');
                            URL.revokeObjectURL(objectUrl);
                        };
                        qrImg.src = objectUrl;
                        qrImg.style.display = 'inline';
                        qrStatus.textContent = 'QR ready. Scan with WhatsApp.';
                        qrStatus.style.color = 'var(--success)';
                        clearInterval(qrInterval);
                    })
                    .catch(err => {
                        console.warn('[QR] Fetch error:', err.message);
                        qrStatus.textContent = 'Waiting for QR... (' + attempts + '/' + maxAttempts + ')';
                    });
            }, 2000);
        }

        function pollPairing(accountId) {
            currentPairingId = accountId;
            const pairingArea = document.getElementById('wa-pairing-area');
            const codeEl = document.getElementById('pairing-code');
            const statusEl = document.getElementById('pairing-status');

            showWaTab('pairing');
            pairingArea.style.display = 'block';
            codeEl.textContent = '------';
            statusEl.textContent = 'Waiting for code...';
            statusEl.style.color = 'var(--text-secondary)';

            pairingInterval = setInterval(() => {
                fetch('/admin/sessions/wa/pairing-status?account_id=' + accountId)
                    .then(res => res.json())
                    .then(data => {
                        if (data.code) {
                            codeEl.textContent = data.code;
                            statusEl.textContent = 'Enter this code in WhatsApp → Settings → Linked Devices → Link with phone number';
                            statusEl.style.color = '#3b82f6';
                        }
                        if (data.status === 'active' || data.status === 'connected') {
                            statusEl.textContent = 'Connected!';
                            statusEl.style.color = 'var(--success)';
                            clearInterval(pairingInterval);
                        } else if (data.status === 'disconnected') {
                            statusEl.textContent = 'Disconnected';
                            statusEl.style.color = 'var(--danger)';
                            clearInterval(pairingInterval);
                        }
                    })
                    .catch(() => {
                        statusEl.textContent = 'Error polling pairing status';
                    });
            }, 3000);
        }

        @if(session('wa_account_id'))
            pollQr('{{ session('wa_account_id') }}');
        @endif
        @if(session('wa_pairing_id'))
            pollPairing('{{ session('wa_pairing_id') }}');
        @endif
    </script>
@endsection
