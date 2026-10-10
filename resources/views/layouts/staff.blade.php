<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Staff - Social CRM')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="auth-user-id" content="{{ Auth::id() }}">
    <style>
        :root {
            --bg-primary: #0f0f0f;
            --bg-secondary: #1a1a1a;
            --bg-card: #242424;
            --border: #333333;
            --text-primary: #ffffff;
            --text-secondary: #9ca3af;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --danger: #ef4444;
            --success: #22c55e;
            --warning: #f59e0b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background-color: var(--bg-secondary);
            border-right: 1px solid var(--border);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 24px 0;
            transition: transform 0.3s ease;
            z-index: 50;
            transform: translateX(0);
        }
        .sidebar.closed {
            transform: translateX(-100%);
        }

        .sidebar-brand {
            padding: 0 24px 24px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 16px;
        }

        .sidebar-brand h1 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .sidebar-nav {
            flex: 1;
            padding: 0 12px;
        }

        .sidebar-nav a {
            display: block;
            padding: 12px 16px;
            margin-bottom: 4px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .sidebar-nav a:hover {
            background-color: var(--bg-card);
            color: var(--text-primary);
        }

        .sidebar-nav a.active {
            background-color: var(--accent);
            color: var(--text-primary);
        }

        .sidebar-footer {
            padding: 16px 12px 0;
            border-top: 1px solid var(--border);
            margin-top: 16px;
        }

        .sidebar-footer form {
            display: block;
        }

        .sidebar-footer button {
            width: 100%;
            padding: 12px 16px;
            background-color: transparent;
            color: var(--text-secondary);
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-align: left;
            transition: all 0.2s;
        }

        .sidebar-footer button:hover {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        /* Main content */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin-left: 250px;
            transition: margin-left 0.3s ease;
        }
        .main-content.expanded { margin-left: 0; }

        /* Top header */
        .top-header {
            height: 64px;
            background-color: var(--bg-secondary);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .top-header .logo {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            text-decoration: none;
        }

        .top-header .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .channel-badge {
            padding: 6px 16px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid var(--border);
            color: var(--text-primary);
            background-color: var(--bg-card);
        }

        .channel-badge.telegram {
            border-color: #3b82f6;
            color: #60a5fa;
        }

        .channel-badge.whatsapp {
            border-color: #22c55e;
            color: #4ade80;
        }

        .user-name {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .logout-btn {
            padding: 8px 16px;
            background-color: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background-color: rgba(239, 68, 68, 0.1);
            border-color: var(--danger);
            color: var(--danger);
        }

        .content-area {
            flex: 1;
            padding: 0;
        }

        .mobile-menu-btn {
            display: none;
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-primary);
            font-size: 20px;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            cursor: pointer;
            align-items: center;
            justify-content: center;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 45;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        .sidebar-overlay.show {
            display: block;
            opacity: 1;
            pointer-events: auto;
        }

        /* Desktop: sidebar visible by default */
        @media (min-width: 769px) {
            .sidebar.closed { transform: translateX(-100%); }
            .sidebar-overlay { display: none !important; }
            .main-content.sidebar-visible { margin-left: 250px; }
            .main-content.expanded { margin-left: 0; }
        }

        /* Mobile: sidebar hidden by default, shown as overlay */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .mobile-menu-btn { display: flex; }
            .top-header { padding: 0 16px; }
        }

        @media (max-width: 480px) {
            .top-header .logo { font-size: 16px; }
            .channel-badge { font-size: 12px; padding: 4px 12px; }
            .user-name { display: none; }
        }
    </style>
</head>
<body>

    <!-- Mobile overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Mobile menu button -->
    <button class="mobile-menu-btn" onclick="toggleMobileMenu()" aria-label="Menu" style="position:fixed;top:12px;left:12px;z-index:45;">
        ☰
    </button>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h1>Social CRM</h1>
        </div>

        <nav class="sidebar-nav">
            <a href="{{ route('staff.dashboard') }}" class="{{ request()->routeIs('staff.dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('staff.channel') }}" class="{{ request()->routeIs('staff.channel') ? 'active' : '' }}">Switch Channel</a>
            <a href="{{ route('staff.changePassword') }}" class="{{ request()->routeIs('staff.changePassword') ? 'active' : '' }}">Change Password</a>
        </nav>

        <div class="sidebar-footer">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit">Logout</button>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content sidebar-visible" id="mainContent">
        <header class="top-header">
            <a href="{{ route('staff.dashboard') }}" class="logo">Social CRM</a>
            <div class="header-right">
                <span class="channel-badge {{ session('channel') }}">
                    @if(session('channel') === 'telegram')
                        ✈ TG Cloud
                    @elseif(session('channel') === 'whatsapp')
                        💬 WP Cloud
                    @else
                        No Channel
                    @endif
                </span>
                <span class="user-name">{{ Auth::user()->name }}</span>
                <form action="{{ route('logout') }}" method="POST" style="display:inline;">
                    @csrf
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </header>

        <main class="content-area">
            @yield('content')
        </main>
    </div>

    <script src="/js/vendor/pusher.js?v=3"></script>
    <script src="/js/vendor/echo.js?v=3"></script>
    <script>
        (function () {
            let retries = 0;
            const maxRetries = 20;

            function initEcho() {
                try {
                    if (typeof Pusher === 'undefined' || typeof Echo === 'undefined') {
                        if (retries < maxRetries) {
                            retries++;
                            setTimeout(initEcho, 100);
                        }
                        return;
                    }

                    const isSecure = window.location.protocol === 'https:';
                    window.Echo = new Echo({
                        broadcaster: 'reverb',
                        key: '{{ config("broadcasting.connections.reverb.key") }}',
                        wsHost: window.location.hostname,
                        wsPort: {{ config("broadcasting.connections.reverb.options.port", 8080) }},
                        wssPort: {{ config("broadcasting.connections.reverb.options.port", 8080) }},
                        forceTLS: isSecure,
                        enabledTransports: isSecure ? ['wss', 'ws'] : ['ws', 'wss'],
                        disableStats: true,
                        authEndpoint: '/broadcasting/auth',
                        auth: {
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                        },
                    });

                    window.Echo.connector.pusher.connection.bind('connected', () => {
                        retries = 0;
                    });

                    window.Echo.connector.pusher.connection.bind('error', (err) => {
                        console.warn('[Staff Realtime] WS error:', err);
                    });

                    window.Echo.connector.pusher.connection.bind('disconnected', () => {
                        console.warn('[Staff Realtime] WS disconnected');
                    });

                    console.log('[Staff Realtime] Echo ready');
                } catch (e) {
                    console.error('[Staff Echo init] Failed:', e.message);
                    window.Echo = undefined;
                }
            }

            initEcho();
        })();
    </script>

    @stack('scripts')

    <script>
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const overlay = document.getElementById('sidebarOverlay');
        const menuBtn = document.querySelector('.mobile-menu-btn');

        function isMobile() {
            return window.innerWidth <= 768;
        }

        function syncSidebarState() {
            if (isMobile()) {
                sidebar.classList.remove('closed');
                overlay.classList.toggle('show', sidebar.classList.contains('open'));
                mainContent.classList.remove('sidebar-visible');
                mainContent.classList.add('expanded');
            } else {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
                if (sidebar.classList.contains('closed')) {
                    mainContent.classList.remove('sidebar-visible');
                    mainContent.classList.add('expanded');
                } else {
                    mainContent.classList.add('sidebar-visible');
                    mainContent.classList.remove('expanded');
                }
            }
        }

        window.toggleMobileMenu = function () {
            if (isMobile()) {
                sidebar.classList.toggle('open');
            } else {
                sidebar.classList.toggle('closed');
            }
            syncSidebarState();
        };

        overlay.addEventListener('click', function () {
            sidebar.classList.remove('open');
            syncSidebarState();
        });

        document.addEventListener('click', function (e) {
            if (!sidebar.contains(e.target) && !menuBtn.contains(e.target)) {
                if (isMobile()) {
                    if (sidebar.classList.contains('open')) {
                        sidebar.classList.remove('open');
                        syncSidebarState();
                    }
                } else {
                    if (sidebar.classList.contains('closed')) {
                        sidebar.classList.remove('closed');
                        syncSidebarState();
                    }
                }
            }
        });

        document.querySelectorAll('.sidebar-nav a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (isMobile()) {
                    sidebar.classList.remove('open');
                } else {
                    sidebar.classList.remove('closed');
                }
                syncSidebarState();
            });
        });

        window.addEventListener('resize', syncSidebarState);
    </script>

</body>
</html>
