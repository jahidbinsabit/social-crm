@extends('layouts.admin')

@section('title', 'Staff Management')
@section('page-title', 'Staff Management')

@section('content')
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

    <!-- Create Staff Form -->
    <div style="background-color: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 24px;">
        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 16px;">Create Staff Member</h3>

        <form method="POST" action="{{ route('admin.staff.store') }}" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            @csrf
            <div style="flex: 1; min-width: 180px;">
                <label style="display: block; font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Name</label>
                <input
                    type="text"
                    name="name"
                    required
                    style="width: 100%; padding: 10px 14px; background-color: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none;"
                >
            </div>

            <div style="flex: 1; min-width: 180px;">
                <label style="display: block; font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Email</label>
                <input
                    type="email"
                    name="email"
                    required
                    style="width: 100%; padding: 10px 14px; background-color: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none;"
                >
            </div>

            <div style="flex: 1; min-width: 180px;">
                <label style="display: block; font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Password</label>
                <input
                    type="password"
                    name="password"
                    required
                    minlength="8"
                    style="width: 100%; padding: 10px 14px; background-color: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none;"
                >
            </div>

            <button type="submit" style="padding: 10px 20px; background-color: var(--accent); color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer;">
                Create Staff
            </button>
        </form>
    </div>

    <!-- Staff Table -->
    <div style="background-color: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; overflow: hidden;">
        <div style="padding: 20px 24px; border-bottom: 1px solid var(--border);">
            <h3 style="font-size: 18px; font-weight: 700;">Staff Members</h3>
            <p style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">{{ $staff->count() }} total</p>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="background-color: var(--bg-primary);">
                        <th style="text-align: left; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">ID</th>
                        <th style="text-align: left; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">Name</th>
                        <th style="text-align: left; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">Email</th>
                        <th style="text-align: left; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">Assigned Accounts</th>
                        <th style="text-align: left; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">Status</th>
                        <th style="text-align: right; padding: 12px 24px; color: var(--text-secondary); font-weight: 600;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($staff as $member)
                        <tr style="border-top: 1px solid var(--border);">
                            <td style="padding: 14px 24px; color: var(--text-secondary);">{{ $member->id }}</td>
                            <td style="padding: 14px 24px; font-weight: 600;">{{ $member->name }}</td>
                            <td style="padding: 14px 24px;">{{ $member->email }}</td>
                            <td style="padding: 14px 24px;">
                                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; background-color: var(--bg-card); color: var(--text-primary); border: 1px solid var(--border);">
                                    {{ $member->accounts->count() }}
                                </span>
                            </td>
                            <td style="padding: 14px 24px;">
                                @php
                                    $statusColor = $member->status === 'active' ? 'var(--success)' : 'var(--danger)';
                                @endphp
                                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; color: {{ $statusColor }}; border: 1px solid {{ $statusColor }}; background-color: rgba(255,255,255,0.02);">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background-color: {{ $statusColor }};"></span>
                                    {{ $member->status }}
                                </span>
                            </td>
                            <td style="padding: 14px 24px; text-align: right;">
                                <form method="POST" action="{{ route('admin.staff.toggle', $member) }}" style="display: inline;" onsubmit="return confirm('Toggle status for this staff member?')">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" style="padding: 6px 12px; background-color: transparent; color: {{ $member->status === 'active' ? 'var(--danger)' : 'var(--success)' }}; border: 1px solid {{ $member->status === 'active' ? 'var(--danger)' : 'var(--success)' }}; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">
                                        {{ $member->status === 'active' ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.staff.destroy', $member) }}" style="display: inline; margin-left: 8px;" onsubmit="return confirm('Delete this staff member permanently?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="padding: 6px 12px; background-color: transparent; color: var(--danger); border: 1px solid var(--danger); border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 40px 24px; text-align: center; color: var(--text-secondary);">
                                No staff members found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
