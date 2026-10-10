<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin - Social CRM')</title>
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
            display: flex;
            min-height: 100vh;
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
            margin-left: 250px;
            flex: 1;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease;
        }

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

        .top-header h2 {
            font-size: 18px;
            font-weight: 600;
        }

        .top-header .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .content-area {
            padding: 32px;
            flex: 1;
        }

        /* Mobile menu button */
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

        /* Sidebar overlay */
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

        /* Responsive tables */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .table-responsive table {
            min-width: 600px;
        }

        /* Desktop: sidebar visible by default */
        @media (min-width: 769px) {
            .sidebar {
                transform: translateX(0);
            }
            .sidebar.closed {
                transform: translateX(-100%);
            }
            .sidebar.closed ~ .main-content {
                margin-left: 0;
            }
            .sidebar:not(.closed) ~ .main-content {
                margin-left: 250px;
            }
            .sidebar-overlay {
                display: none !important;
            }
        }

        /* Mobile: sidebar hidden by default, shown as overlay */
        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: flex;
            }
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .top-header {
                padding: 0 16px;
            }
            .content-area {
                padding: 16px;
            }
            .sidebar-nav a {
                padding: 14px 16px;
                font-size: 15px;
            }
        }

        @media (max-width: 480px) {
            .top-header h2 {
                font-size: 16px;
            }
            .content-area {
                padding: 12px;
            }
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
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('admin.accounts') }}" class="{{ request()->routeIs('admin.accounts') ? 'active' : '' }}">Accounts</a>
            <a href="{{ route('admin.campaigns') }}" class="{{ request()->routeIs('admin.campaigns') ? 'active' : '' }}">Campaign</a>
            <a href="{{ route('admin.assign') }}" class="{{ request()->routeIs('admin.assign') ? 'active' : '' }}">Assign</a>
            <a href="{{ route('admin.sessions') }}" class="{{ request()->routeIs('admin.sessions') ? 'active' : '' }}">Account Session</a>
            <a href="{{ route('admin.settings') }}" class="{{ request()->routeIs('admin.settings') ? 'active' : '' }}">Settings</a>
            <a href="{{ route('admin.staff') }}" class="{{ request()->routeIs('admin.staff') ? 'active' : '' }}">Staff Management</a>
        </nav>

        <div class="sidebar-footer">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit">Logout</button>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <header class="top-header">
            <h2>@yield('page-title', 'Dashboard')</h2>
            <div class="user-info">
                <span>{{ Auth::user()->name }}</span>
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
                        console.warn('[Admin Realtime] WS error:', err);
                    });

                    window.Echo.connector.pusher.connection.bind('disconnected', () => {
                        console.warn('[Admin Realtime] WS disconnected');
                    });

                    console.log('[Admin Realtime] Echo ready');
                } catch (e) {
                    console.error('[Admin Echo init] Failed:', e.message);
                    window.Echo = undefined;
                }
            }

            initEcho();
        })();
    </script>

    @stack('scripts')

    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const menuBtn = document.querySelector('.mobile-menu-btn');

        function isMobile() {
            return window.innerWidth <= 768;
        }

        function syncSidebarState() {
            if (isMobile()) {
                overlay.classList.toggle('show', sidebar.classList.contains('open'));
            } else {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
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
            if (isMobile()) {
                sidebar.classList.remove('open');
            } else {
                sidebar.classList.remove('closed');
            }
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
