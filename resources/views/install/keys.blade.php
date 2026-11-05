@extends('install.layout')

@section('content')
    <h2>Keys &amp; services</h2>
    <p class="stepdesc">All keys were auto-generated for you — most installs should simply keep them. Nothing here needs to be typed into code later: the wizard writes every value into <span class="mono">.env</span> and prepares the shared engine secret for PM2.</p>

    <form method="POST" action="{{ route('install.keys.next') }}">
        @csrf

        <div class="field">
            <label for="app_key">Application key (APP_KEY)</label>
            <input type="text" id="app_key" name="app_key" class="mono" value="{{ old('app_key', $keys['app_key']) }}" required>
            <p class="hint">Used to encrypt sessions and stored credentials. Changing it later logs everyone out and makes previously encrypted data unreadable.</p>
        </div>

        <div class="field">
            <label for="internal_secret">Internal API secret (INTERNAL_API_SECRET)</label>
            <input type="text" id="internal_secret" name="internal_secret" class="mono" value="{{ old('internal_secret', $keys['internal_secret']) }}" required>
            <p class="hint">Shared secret between Laravel and the WhatsApp / Telegram engines. Anyone who knows it can inject messages — keep it private.</p>
        </div>

        <div class="row">
            <div class="field">
                <label for="reverb_app_id">Reverb app ID</label>
                <input type="text" id="reverb_app_id" name="reverb_app_id" class="mono" value="{{ old('reverb_app_id', $keys['reverb_app_id']) }}" required>
            </div>
            <div class="field">
                <label for="reverb_app_key">Reverb app key</label>
                <input type="text" id="reverb_app_key" name="reverb_app_key" class="mono" value="{{ old('reverb_app_key', $keys['reverb_app_key']) }}" required>
            </div>
        </div>

        <div class="field">
            <label for="reverb_app_secret">Reverb app secret</label>
            <input type="text" id="reverb_app_secret" name="reverb_app_secret" class="mono" value="{{ old('reverb_app_secret', $keys['reverb_app_secret']) }}" required>
            <p class="hint">Reverb (real-time chat) runs locally on 127.0.0.1:8080 — no form fields needed for it.</p>
        </div>

        <div class="row">
            <div class="field" style="flex:3;">
                <label for="redis_host">Redis host</label>
                <input type="text" id="redis_host" name="redis_host" value="{{ old('redis_host', $keys['redis_host']) }}" required>
            </div>
            <div class="field" style="flex:1;">
                <label for="redis_port">Redis port</label>
                <input type="number" id="redis_port" name="redis_port" value="{{ old('redis_port', $keys['redis_port']) }}" required min="1" max="65535">
            </div>
        </div>

        <div class="field">
            <label for="redis_password">Redis password</label>
            {{-- Never re-rendered: blank keeps the stored value, and most local Redis installs have no password at all. --}}
            <input type="password" id="redis_password" name="redis_password" value="" autocomplete="new-password">
            <p class="hint">Leave blank if Redis has no password (the usual local setup).@if (!empty($keys['redis_password_set'])) A password is already saved — blank keeps it.@endif</p>
        </div>

        <div class="note">
            <strong>Engine ports are fixed:</strong> WhatsApp engine <span class="mono">http://127.0.0.1:3001</span> · Telegram engine <span class="mono">http://127.0.0.1:3002</span>. They only listen on this machine and authenticate with the internal secret above.
        </div>

        <div class="actions">
            <a class="btn btn-secondary" href="{{ route('install.database') }}">&larr; Back</a>
            <button type="submit" class="btn">Next: Administrator &rarr;</button>
        </div>
    </form>
@endsection
