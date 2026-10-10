@extends('layouts.admin')

@section('title', 'Settings')
@section('page-title', 'Settings')

@section('content')
    @if(session('success'))
        <div style="background-color: rgba(34, 197, 94, 0.1); border: 1px solid var(--success); color: #86efac; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px;">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">

            <!-- Card 1: Delay & Limits -->
            <div style="background-color: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
                <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">Delay & Limits</h3>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Min Delay (seconds)</label>
                    <input
                        type="number"
                        name="min_delay_seconds"
                        value="{{ old('min_delay_seconds', $settings->min_delay_seconds) }}"
                        required
                        min="5"
                        max="300"
                        style="width: 100%; padding: 10px 14px; background-color: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none;"
                    >
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Max Delay (seconds)</label>
                    <input
                        type="number"
                        name="max_delay_seconds"
                        value="{{ old('max_delay_seconds', $settings->max_delay_seconds) }}"
                        required
                        min="5"
                        max="300"
                        style="width: 100%; padding: 10px 14px; background-color: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none;"
                    >
                </div>

                <div>
                    <label style="display: block; font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Daily Limit Per Account</label>
                    <input
                        type="number"
                        name="daily_limit_per_account"
                        value="{{ old('daily_limit_per_account', $settings->daily_limit_per_account) }}"
                        required
                        min="1"
                        max="100"
                        style="width: 100%; padding: 10px 14px; background-color: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none;"
                    >
                </div>
            </div>

            <!-- Card 2: Proxy -->
            <div style="background-color: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
                <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">Admin Outbound Proxy</h3>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Proxy IP</label>
                    <input
                        type="text"
                        name="proxy_ip"
                        value="{{ old('proxy_ip', $settings->proxy_ip) }}"
                        placeholder="Optional"
                        style="width: 100%; padding: 10px 14px; background-color: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none;"
                    >
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Proxy Port</label>
                    <input
                        type="number"
                        name="proxy_port"
                        value="{{ old('proxy_port', $settings->proxy_port) }}"
                        placeholder="Optional"
                        min="1"
                        max="65535"
                        style="width: 100%; padding: 10px 14px; background-color: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none;"
                    >
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Proxy Username</label>
                    <input
                        type="text"
                        name="proxy_username"
                        value="{{ old('proxy_username', $settings->proxy_username) }}"
                        placeholder="Optional"
                        style="width: 100%; padding: 10px 14px; background-color: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none;"
                    >
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Proxy Password</label>
                    <input
                        type="password"
                        name="proxy_password"
                        value="{{ old('proxy_password', $settings->proxy_password) }}"
                        placeholder="Optional"
                        style="width: 100%; padding: 10px 14px; background-color: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none;"
                    >
                </div>
            </div>

        </div>

        <button type="submit" style="padding: 10px 20px; background-color: var(--accent); color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer;">
            Save Settings
        </button>
    </form>
@endsection
