@extends('install.layout')

@section('content')
    <h2>Review &amp; install</h2>
    <p class="stepdesc">Check everything once more. Pressing <strong>Install</strong> writes the <span class="mono">.env</span> file, runs all database migrations, seeds the default settings, creates your admin account, and closes this wizard permanently. All secrets below are masked.</p>

    @php
        $maskSet = fn ($v) => !empty($v) ? '•••••••• (set)' : '(empty)';
        $maskKey = fn ($v) => empty($v) ? '(empty)' : substr($v, 0, 10) . '••••••';
    @endphp

    <h2 style="font-size:16px;margin-top:10px;">Application</h2>
    <dl class="review">
        <dt>Name</dt><dd>{{ $db['app_name'] }}</dd>
        <dt>URL</dt><dd>{{ $db['app_url'] }}</dd>
        <dt>Environment</dt><dd>production (debug off)</dd>
    </dl>

    <h2 style="font-size:16px;">Database (MySQL)</h2>
    <dl class="review">
        <dt>Host</dt><dd>{{ $db['db_host'] }}:{{ $db['db_port'] }}</dd>
        <dt>Database</dt><dd>{{ $db['db_database'] }}</dd>
        <dt>Username</dt><dd>{{ $db['db_username'] }}</dd>
        <dt>Password</dt><dd>{{ $maskSet($db['db_password'] ?? '') }}</dd>
    </dl>

    <h2 style="font-size:16px;">Keys &amp; services</h2>
    <dl class="review">
        <dt>APP_KEY</dt><dd class="mono">{{ $maskKey($keys['app_key']) }}</dd>
        <dt>Internal API secret</dt><dd class="mono">{{ $maskKey($keys['internal_secret']) }}</dd>
        <dt>Reverb app ID</dt><dd class="mono">{{ $keys['reverb_app_id'] }}</dd>
        <dt>Reverb app key</dt><dd class="mono">{{ $maskKey($keys['reverb_app_key']) }}</dd>
        <dt>Reverb app secret</dt><dd class="mono">{{ $maskKey($keys['reverb_app_secret']) }}</dd>
        <dt>Reverb server</dt><dd>127.0.0.1:8080 (local)</dd>
        <dt>Redis</dt><dd>{{ $keys['redis_host'] }}:{{ $keys['redis_port'] }}, password {{ $maskSet($keys['redis_password'] ?? '') }}</dd>
        <dt>WhatsApp engine</dt><dd>http://127.0.0.1:3001</dd>
        <dt>Telegram engine</dt><dd>http://127.0.0.1:3002</dd>
    </dl>

    <h2 style="font-size:16px;">Administrator</h2>
    <dl class="review">
        <dt>Name</dt><dd>{{ $admin['name'] }}</dd>
        <dt>Email</dt><dd>{{ $admin['email'] }}</dd>
        <dt>Password</dt><dd>{{ $maskSet($admin['password'] ?? '') }}</dd>
    </dl>

    <form method="POST" action="{{ route('install.finish') }}">
        @csrf
        <div class="actions">
            <a class="btn btn-secondary" href="{{ route('install.admin') }}">&larr; Back</a>
            <button type="submit" class="btn">Install Social CRM</button>
        </div>
    </form>
@endsection
