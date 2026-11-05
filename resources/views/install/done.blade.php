@extends('install.layout')

@section('content')
    <div style="text-align:center;font-size:44px;line-height:1;margin-bottom:14px;">✅</div>
    <h2 style="text-align:center;">Installation complete</h2>
    <p class="stepdesc" style="text-align:center;">The <span class="mono">.env</span> file has been written, the database migrated, default settings seeded, and your admin account created. This wizard is now permanently closed — visiting <span class="mono">/install</span> again will send you to the login page.</p>

    <div style="text-align:center;margin-bottom:28px;">
        <a class="btn" href="{{ url('/login') }}">Go to login &rarr;</a>
    </div>

    <h2 style="font-size:16px;">Last step — start the background services</h2>
    <p class="stepdesc">The web installer cannot start long-running processes for you. Run these commands once on the server. PM2 starts <strong>everything</strong> defined in <span class="mono">ecosystem.config.cjs</span>: the web app (port 8001), the Horizon queue worker, the Reverb WebSocket server (port 8080), and both messaging engines.</p>

<pre class="cmds">cd {{ $basePath }}

# 1) The engines authenticate with the same secret the wizard generated.
#    Export it into the shell PM2 inherits (written for you, chmod 600):
source storage/app/installer/engine-env.sh

# 2) Start all services (web + Horizon + Reverb + WhatsApp + Telegram):
pm2 start ecosystem.config.cjs
pm2 save</pre>

    <div class="note">
        <strong>Before starting PM2, check two paths in <span class="mono">ecosystem.config.cjs</span>:</strong><br>
        1) Every <span class="mono">cwd</span> entry must point at this server's project folder (<span class="mono">{{ $basePath }}</span>) — the shipped file contains a development-machine path.<br>
        2) The Telegram engine's <span class="mono">interpreter</span> must point at its virtualenv (<span class="mono">services/telegram-engine/venv/bin/python3</span>) — create it and install <span class="mono">requirements.txt</span> first if you have not (see README, “Telegram Engine Dependencies”).
    </div>

    <div class="note">
        <strong>Not using PM2?</strong> Run these in separate terminals instead: <span class="mono">php artisan serve --port=8001</span> · <span class="mono">php artisan horizon</span> · <span class="mono">php artisan reverb:start</span> · <span class="mono">node services/whatsapp-engine/index.js</span> (with <span class="mono">INTERNAL_API_SECRET</span> exported) · the Telegram engine via its venv python. Queue, cache and broadcasting all expect Redis to be running.
    </div>

    <div class="note">
        <strong>Keep safe:</strong> your previous <span class="mono">.env</span> (if any) was backed up next to the new one as <span class="mono">.env.bak-*</span>, and <span class="mono">storage/app/installer/engine-env.sh</span> contains the internal secret — it is permission-locked (600) and must never be committed to git.
    </div>
@endsection
