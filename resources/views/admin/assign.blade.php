@extends('layouts.admin')

@section('title', 'Assign')
@section('page-title', 'Assign')

@section('content')
<style>
.assign-layout{display:grid;grid-template-columns:320px 1fr;gap:20px;align-items:start}
.staff-card{background:var(--bg-secondary);border:1px solid var(--border);border-radius:10px;padding:12px 14px;cursor:pointer;transition:all .15s}
.staff-card:hover{border-color:var(--platform-color);transform:translateY(-1px)}
.staff-card.active{border-color:var(--platform-color);box-shadow:0 0 0 2px rgba(59,130,246,.25)}
.staff-name{font-weight:700;font-size:15px;color:var(--text-primary)}
.staff-email{font-size:12px;color:var(--text-secondary);margin-top:2px}
.staff-meta{font-size:12px;color:var(--text-secondary);margin-top:6px}
.platform-tabs{display:flex;gap:8px;margin-bottom:14px}
.platform-tab{flex:1;padding:10px;border-radius:8px;border:1px solid var(--border);background:var(--bg-secondary);color:var(--text-secondary);cursor:pointer;font-weight:600;text-align:center;transition:all .15s}
.platform-tab.active{background:var(--platform-color);color:#fff;border-color:var(--platform-color)}
.account-row{display:flex;align-items:center;justify-content:space-between;padding:12px;border-radius:8px;border:1px solid var(--border);background:var(--bg-primary);margin-bottom:10px}
.account-row .info{min-width:0;flex:1;margin-right:12px}
.account-row .name{font-weight:600;font-size:14px;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.account-row .phone{font-size:12px;color:var(--text-secondary);margin-top:2px}
.toggle{width:44px;height:24px;border-radius:999px;border:none;cursor:pointer;position:relative;transition:background .15s;flex-shrink:0}
.toggle::after{content:'';position:absolute;top:2px;left:2px;width:20px;height:20px;border-radius:50%;background:#fff;transition:transform .15s}
.toggle.on{background:#22c55e}
.toggle.off{background:#333}
.toggle.on::after{transform:translateX(20px)}
.empty-state{color:var(--text-secondary);font-size:13px;padding:14px}
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:700;margin-left:8px}
.badge-tg{background:rgba(59,130,246,.15);color:#60a5fa;border:1px solid #3b82f6}
.badge-wa{background:rgba(34,197,94,.15);color:#4ade80;border:1px solid #22c55e}
</style>

<div class="assign-layout">
    <div style="background:var(--bg-secondary);border:1px solid var(--border);border-radius:12px;padding:18px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
            <h3 style="margin:0;font-size:16px;font-weight:700">Staff Members</h3>
            <div style="display:flex;align-items:center;gap:8px">
                <button onclick="triggerAutoAssign()" style="padding:4px 10px;font-size:12px;font-weight:600;background:var(--platform-color);color:#fff;border:none;border-radius:6px;cursor:pointer">⚡ Auto Assign</button>
                <span id="staff-count" class="badge">0</span>
            </div>
        </div>
        <div id="staff-list" style="display:flex;flex-direction:column;gap:8px">
            @foreach($staff as $member)
                <div class="staff-card" data-staff="{{ $member->id }}" onclick="selectStaff({{ $member->id }})">
                    <div class="staff-name">{{ $member->name }}</div>
                    <div class="staff-email">{{ $member->email }}</div>
                    <div class="staff-meta">
                        <span id="staff-platform-count-{{ $member->id }}"></span>
                        <span style="margin-left:8px" id="staff-account-count-{{ $member->id }}"></span>
                    </div>
                </div>
            @endforeach
            @if($staff->isEmpty())
                <div class="empty-state">No active staff members found.</div>
            @endif
        </div>
    </div>
    <div style="background:var(--bg-secondary);border:1px solid var(--border);border-radius:12px;padding:18px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
            <h3 style="margin:0;font-size:16px;font-weight:700">Assigned Accounts</h3>
            <span id="account-count" class="badge">0</span>
        </div>
        <div id="platform-tabs" class="platform-tabs" style="display:none">
            <button class="platform-tab active" id="tab-telegram" onclick="switchPlatform('telegram')">Telegram</button>
            <button class="platform-tab" id="tab-whatsapp" onclick="switchPlatform('whatsapp')">WhatsApp</button>
        </div>
        <div id="account-list">
            <div class="empty-state">Select a staff member to view accounts</div>
        </div>
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
let selectedStaffId = null;
let selectedPlatform = 'telegram';

function selectStaff(staffId) {
    selectedStaffId = staffId;
    selectedPlatform = 'telegram';
    document.querySelectorAll('.staff-card').forEach(c => c.classList.remove('active'));
    document.querySelector('.staff-card[data-staff="' + staffId + '"]').classList.add('active');
    document.getElementById('platform-tabs').style.display = 'flex';
    document.getElementById('tab-telegram').classList.add('active');
    document.getElementById('tab-whatsapp').classList.remove('active');
    loadAccounts('telegram');
    loadStaffSummary(staffId);
}

async function loadStaffSummary(staffId) {
    try {
        const res = await fetch('{{ url("admin/assign/staff") }}/' + staffId + '/accounts');
        const accounts = await res.json();
        const assigned = accounts.filter(a => a.assigned);
        const tg = assigned.filter(a => a.platform === 'telegram').length;
        const wa = assigned.filter(a => a.platform === 'whatsapp').length;
        const el = document.getElementById('staff-platform-count-' + staffId);
        if (el) el.innerHTML = (tg > 0 ? '<span class="badge badge-tg">TG ' + tg + '</span>' : '') + (wa > 0 ? '<span class="badge badge-wa">WA ' + wa + '</span>' : '');
        const el2 = document.getElementById('staff-account-count-' + staffId);
        if (el2) el2.textContent = '(' + assigned.length + '/' + accounts.length + ' assigned)';
    } catch (e) {}
}

function switchPlatform(platform) {
    selectedPlatform = platform;
    document.getElementById('tab-telegram').classList.toggle('active', platform === 'telegram');
    document.getElementById('tab-whatsapp').classList.toggle('active', platform === 'whatsapp');
    loadAccounts(platform);
}

async function loadAccounts(platform) {
    if (!selectedStaffId) return;
    const list = document.getElementById('account-list');
    list.innerHTML = '<div class="empty-state">Loading...</div>';
    try {
        const res = await fetch('{{ url("admin/assign/staff") }}/' + selectedStaffId + '/accounts?platform=' + platform);
        const accounts = await res.json();
        document.getElementById('account-count').textContent = accounts.length;
        if (accounts.length === 0) {
            list.innerHTML = '<div class="empty-state">No ' + platform + ' accounts assigned</div>';
            return;
        }
        list.innerHTML = accounts.map(function(acc) {
            return '<div class="account-row"><div class="info"><div class="name">' + acc.account_name + '</div><div class="phone">' + acc.phone_number + '</div></div><button class="toggle ' + (acc.assigned ? 'on' : 'off') + '" data-account="' + acc.id + '" onclick="toggleAssign(this, ' + acc.id + ')"></button></div>';
        }).join('');
    } catch (e) {
        list.innerHTML = '<div class="empty-state">Failed to load accounts</div>';
    }
}

async function toggleAssign(btn, accountId) {
    btn.disabled = true;
    const isAssigned = btn.classList.contains('on');
    const url = isAssigned ? '{{ route("admin.assign.deassign") }}' : '{{ route("admin.assign.assign") }}';
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken},
            body: JSON.stringify({staff_id: selectedStaffId, account_id: accountId})
        });
        const data = await res.json();
        if (data.success) {
            if (isAssigned) {
                btn.classList.remove('on');
                btn.classList.add('off');
            } else {
                btn.classList.remove('off');
                btn.classList.add('on');
            }
            loadAccounts(selectedPlatform);
            loadStaffSummary(selectedStaffId);
        } else {
            alert('Failed to update assignment');
        }
    } catch (e) {
        alert('Failed to update assignment');
    } finally {
        btn.disabled = false;
    }
}

async function triggerAutoAssign() {
    if (!confirm('Are you sure you want to automatically distribute active accounts evenly among all active staff members?')) return;
    try {
        const res = await fetch('{{ route("admin.assign.auto") }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken}
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || 'Auto-assign failed');
        }
    } catch (e) {
        alert('Auto-assign request failed');
    }
}

document.getElementById('staff-count').textContent = {{ $staff->count() }};
</script>
@endsection
