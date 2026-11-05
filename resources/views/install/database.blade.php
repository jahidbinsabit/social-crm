@extends('install.layout')

@section('content')
    <h2>Application &amp; database</h2>
    <p class="stepdesc">Where the CRM will live, and the MySQL database it should use. The database must already exist — create it in your hosting panel or with <span class="mono">CREATE DATABASE</span> first. The connection is tested before you can continue.</p>

    <form method="POST" action="{{ route('install.database.next') }}">
        @csrf

        <div class="field">
            <label for="app_name">Application name</label>
            <input type="text" id="app_name" name="app_name" value="{{ old('app_name', $db['app_name'] ?? 'Social CRM') }}" required maxlength="60">
        </div>

        <div class="field">
            <label for="app_url">Application URL</label>
            <input type="url" id="app_url" name="app_url" value="{{ old('app_url', $db['app_url'] ?? $defaultUrl) }}" required>
            <p class="hint">The exact address visitors will use, e.g. <span class="mono">https://crm.example.com</span> — no trailing slash. Use the real domain: session security and the WebSocket allow-list are derived from it.</p>
        </div>

        <div class="row">
            <div class="field" style="flex:3;">
                <label for="db_host">Database host</label>
                <input type="text" id="db_host" name="db_host" value="{{ old('db_host', $db['db_host'] ?? '127.0.0.1') }}" required>
            </div>
            <div class="field" style="flex:1;">
                <label for="db_port">Port</label>
                <input type="number" id="db_port" name="db_port" value="{{ old('db_port', $db['db_port'] ?? 3306) }}" required min="1" max="65535">
            </div>
        </div>

        <div class="row">
            <div class="field">
                <label for="db_database">Database name</label>
                <input type="text" id="db_database" name="db_database" value="{{ old('db_database', $db['db_database'] ?? 'social_crm') }}" required>
            </div>
            <div class="field">
                <label for="db_username">Database username</label>
                <input type="text" id="db_username" name="db_username" value="{{ old('db_username', $db['db_username'] ?? '') }}" required autocomplete="off">
            </div>
        </div>

        <div class="field">
            <label for="db_password">Database password</label>
            {{-- Passwords are never re-rendered. A blank field keeps the password already stored in the wizard session. --}}
            <input type="password" id="db_password" name="db_password" value="" autocomplete="new-password">
            @if ($passwordStored)
                <p class="hint">A password is already saved from your earlier entry — leave this blank to keep it, or type a new one to replace it.</p>
            @endif
        </div>

        <div class="actions">
            <a class="btn btn-secondary" href="{{ route('install.requirements') }}">&larr; Back</a>
            <button type="submit" class="btn">Test &amp; continue &rarr;</button>
        </div>
    </form>
@endsection
