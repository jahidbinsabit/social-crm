@extends('layouts.admin')

@section('title', 'Accounts')
@section('page-title', 'Accounts')

@section('content')
    <div class="admin-accounts-grid">

        <!-- Column 1: Account List -->
        <div class="card account-list-card">
            <div class="card-header">
                <div class="platform-tabs">
                    <a href="{{ route('admin.accounts') }}"
                       class="pill {{ empty($platform) ? 'pill-active' : 'pill-ghost' }}">
                        All
                    </a>
                    <a href="{{ route('admin.accounts', ['platform' => 'telegram']) }}"
                       class="pill {{ $platform === 'telegram' ? 'pill-active' : 'pill-ghost' }}">
                        ✈ Telegram
                    </a>
                    <a href="{{ route('admin.accounts', ['platform' => 'whatsapp']) }}"
                       class="pill {{ $platform === 'whatsapp' ? 'pill-active' : 'pill-ghost' }}">
                        💬 WhatsApp
                    </a>
                </div>
                <div class="card-title-row">
                    <h3>Accounts</h3>
                    <span class="badge">{{ $accounts->count() }}</span>
                </div>
            </div>
            <div id="account-list" class="scroll-area">
                @foreach($accounts as $account)
                    <div class="account-item"
                         data-account-id="{{ $account->id }}">
                        <div class="account-row">
                            <span class="account-icon">
                                @if($account->platform === 'telegram')
                                    ✈
                                @else
                                    💬
                                @endif
                            </span>
                            <div class="account-meta">
                                <div class="account-name">{{ $account->account_name }}</div>
                                <div class="account-phone">{{ $account->phone_number }}</div>
                                <div class="account-status">
                                    <span class="status-dot {{ $account->status === 'active' ? 'status-active' : ($account->status === 'connecting' ? 'status-warn' : 'status-danger') }}"></span>
                                    <span class="status-text">{{ ucfirst($account->status) }}</span>
                                    <span id="unread-badge-{{ $account->id }}" class="unread-badge" style="display:none;">0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                @if($accounts->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <p>No accounts found</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Column 2: Chat List -->
        <div class="card chat-card">
            <div class="card-header">
                <div class="card-title-row">
                    <h3>Chats</h3>
                    <span id="chat-list-subtitle" class="subtitle">Select an account</span>
                </div>
            </div>
            <div id="chat-list" class="scroll-area">
                <div class="empty-state">
                    <p>Select an account to view chats</p>
                </div>
            </div>
        </div>

        <!-- Column 3: Message Inspector -->
        <div class="card message-card">
            <div class="card-header">
                <div class="card-title-row">
                    <h3 id="message-title">Messages</h3>
                    <span id="message-subtitle" class="subtitle">Select a chat</span>
                </div>
            </div>
            <div id="message-list" class="scroll-area message-body">
                <div class="empty-state">
                    <div class="empty-icon-large">💬</div>
                    <p>Select a conversation to start</p>
                </div>
            </div>
            <div id="message-input-area" class="reply-area">
                <div id="admin-attach-preview" class="attach-preview" style="display:none;">
                    <span id="admin-ap-icon" class="attach-icon">📄</span>
                    <span style="flex:1; min-width:0;">
                        <span id="admin-ap-name" class="attach-name"></span>
                        <span id="admin-ap-size" class="attach-size"></span>
                    </span>
                    <button type="button" onclick="adminClearAttachment()" class="icon-btn">×</button>
                </div>
                <form id="reply-form" class="reply-form">
                    @csrf
                    <input type="file" id="admin-file-input" style="display:none" onchange="adminOnFilePicked(event)">
                    <button type="button" onclick="document.getElementById('admin-file-input').click()" class="icon-btn" title="Attach file">
                        📎
                    </button>
                    <textarea id="reply-text" rows="2" placeholder="Type a reply..." class="reply-text"></textarea>
                    <button type="submit" class="btn-primary">Send</button>
                </form>
            </div>
        </div>

    </div>

    <style>
        .admin-accounts-grid {
            display: grid;
            grid-template-columns: 240px 260px 1fr;
            gap: 12px;
            height: calc(100vh - 120px);
            min-height: 520px;
        }

        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .card-header {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .platform-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .pill {
            padding: 6px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--border);
            white-space: nowrap;
        }

        .pill-active {
            background-color: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }

        .pill-ghost {
            background-color: var(--bg-card);
            color: var(--text-secondary);
            border-color: var(--border);
        }

        .card-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .card-title-row h3 {
            font-size: 15px;
            font-weight: 700;
        }

        .subtitle {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .badge {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-secondary);
            background: var(--bg-card);
            padding: 2px 8px;
            border-radius: 9999px;
            border: 1px solid var(--border);
        }

        .scroll-area {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }

        .account-item {
            padding: 10px;
            margin-bottom: 8px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .account-item:hover {
            border-color: #3b82f6;
        }

        .account-item.active {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.08);
        }

        .account-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .account-icon {
            font-size: 16px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            flex-shrink: 0;
        }

        .account-meta {
            min-width: 0;
            flex: 1;
        }

        .account-name {
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .account-phone {
            font-size: 11px;
            color: var(--text-secondary);
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .account-status {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 6px;
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
        }

        .status-active { background-color: var(--success); }
        .status-warn { background-color: var(--warning); }
        .status-danger { background-color: var(--danger); }

        .status-text {
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: capitalize;
        }

        .unread-badge {
            margin-left: auto;
            background: var(--danger);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 9999px;
        }

        .empty-state {
            text-align: center;
            padding: 36px 20px;
            color: var(--text-secondary);
        }

        .empty-icon {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .empty-icon-large {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .message-body {
            padding: 16px;
        }

        .reply-area {
            padding: 12px 16px;
            border-top: 1px solid var(--border);
            display: none;
            flex-direction: column;
            gap: 10px;
        }

        .attach-preview {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
        }

        .attach-icon {
            font-size: 20px;
        }

        .attach-name {
            display: block;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .attach-size {
            display: block;
            font-size: 11px;
            color: var(--text-secondary);
        }

        .icon-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 18px;
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            line-height: 1;
        }

        .reply-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .reply-text {
            flex: 1;
            padding: 10px 12px;
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            outline: none;
            resize: none;
            font-family: Arial, Helvetica, sans-serif;
        }

        .btn-primary {
            padding: 10px 16px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .chat-item {
            padding: 10px;
            margin-bottom: 8px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .chat-item:hover {
            border-color: #3b82f6;
        }

        .chat-item.active {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.08);
        }

        /* Tablet */
        @media (max-width: 1024px) {
            .admin-accounts-grid {
                grid-template-columns: 200px 1fr;
                grid-template-rows: 1fr 1fr;
            }

            .account-list-card {
                grid-row: 1 / 3;
            }
        }

        /* Mobile */
        @media (max-width: 768px) {
            .admin-accounts-grid {
                grid-template-columns: 1fr;
                grid-template-rows: auto;
                height: auto;
                min-height: 0;
            }

            .scroll-area {
                max-height: 45vh;
            }

            .message-body {
                max-height: 50vh;
            }
        }

        @media (max-width: 480px) {
            .platform-tabs {
                gap: 6px;
            }
            .pill {
                font-size: 11px;
                padding: 5px 10px;
            }
            .reply-form {
                flex-wrap: wrap;
            }
            .reply-text {
                min-width: 0;
                width: 100%;
            }
            .btn-primary {
                width: 100%;
            }
        }
        /* Realtime Toast Notifications */
        .admin-toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 100000;
            display: flex;
            flex-direction: column-reverse;
            gap: 12px;
            pointer-events: none;
            max-width: 390px;
            width: calc(100vw - 32px);
        }

        .admin-toast {
            pointer-events: auto;
            background: rgba(26, 26, 26, 0.96);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            padding: 13px 15px 11px;
            box-shadow: 0 16px 36px -4px rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: transform 0.18s ease, background 0.18s ease;
            animation: adminToastSlideIn 0.32s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            color: #fff;
        }

        .admin-toast.tg-theme { border-left: 4px solid #229ED9; }
        .admin-toast.wa-theme { border-left: 4px solid #25D366; }
        .admin-toast:hover { transform: translateY(-2px); background: rgba(36, 36, 36, 0.98); }
        .admin-toast.hiding { animation: adminToastSlideOut 0.25s cubic-bezier(0.4, 0, 1, 1) forwards; }

        @keyframes adminToastSlideIn {
            from { opacity: 0; transform: translateY(30px) scale(0.94); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes adminToastSlideOut {
            from { opacity: 1; transform: translateY(0) scale(1); }
            to { opacity: 0; transform: translateY(20px) scale(0.92); }
        }

        .admin-toast-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .admin-toast-user { display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1; }
        .admin-toast-avatar { width: 34px; height: 34px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; flex-shrink: 0; }
        .admin-toast-info { min-width: 0; flex: 1; }
        .admin-toast-name { font-size: 13px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .admin-toast-account { font-size: 11px; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .admin-toast-close { width: 20px; height: 20px; border-radius: 50%; border: none; background: rgba(255,255,255,0.08); color: #aaa; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px; }
        .admin-toast-close:hover { background: rgba(239,68,68,0.3); color: #ef4444; }
        .admin-toast-body { margin-top: 6px; padding-left: 44px; font-size: 12px; color: #d1d5db; line-height: 1.4; word-break: break-word; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .admin-toast-footer { display: flex; align-items: center; justify-content: flex-end; margin-top: 6px; padding-left: 44px; font-size: 11px; font-weight: 500; color: #38bdf8; }
        .admin-toast-progress { position: absolute; bottom: 0; left: 0; width: 100%; height: 2.5px; transform-origin: left; animation: adminToastProg 7s linear forwards; }
        @keyframes adminToastProg { from { transform: scaleX(1); } to { transform: scaleX(0); } }
    </style>

    <script>
        var currentAccountId = null;
        var currentClientId = null;
        var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        document.querySelectorAll('.account-item').forEach(function(item) {
            item.addEventListener('click', function() {
                document.querySelectorAll('.account-item').forEach(function(el) {
                    el.classList.remove('active');
                });
                this.classList.add('active');

                currentAccountId = this.dataset.accountId;
                loadChats(currentAccountId);
            });
        });

        function getClientDisplayName(client) {
            if (!client) return '';
            var name = (client.display_name || '').trim();
            var genericNames = ['client', 'contact', 'crm contact', 'user', 'unknown', 'none', 'null'];
            if (name && !genericNames.includes(name.toLowerCase())) {
                return name;
            }
            var ident = (client.identifier || '').trim();
            if (client.platform === 'telegram') {
                if (ident.startsWith('+') || /^\d+$/.test(ident)) {
                    return ident.startsWith('+') ? ident : ('+' + ident);
                }
                return ident.startsWith('@') ? ident : ('@' + ident);
            }
            return formatPhone(ident);
        }

        function loadChats(accountId, silent) {
            var chatList = document.getElementById('chat-list');
            if (!silent) {
                chatList.innerHTML = '<div class="empty-state"><p>Loading chats...</p></div>';
            }

            fetch('/admin/accounts/' + accountId + '/chats')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var chats = Array.isArray(data) ? data : (data.chats || []);
                    document.getElementById('chat-list-subtitle').textContent = chats.length + ' chats';

                    if (chats.length === 0) {
                        chatList.innerHTML = '<div class="empty-state"><p>No chats yet</p></div>';
                        return;
                    }

                    var html = '';
                    chats.forEach(function(client) {
                        var lastMessage = client.messages && client.messages[0];
                        var preview = 'No messages';
                        if (lastMessage) {
                            preview = (lastMessage.message_type && lastMessage.message_type !== 'text')
                                ? previewText(lastMessage.message_body, lastMessage.message_type, lastMessage.media_name)
                                : (lastMessage.message_body || '');
                        } else if (client.last_message) {
                            preview = (client.last_message_type && client.last_message_type !== 'text')
                                ? previewText(client.last_message, client.last_message_type, '')
                                : (client.last_message || '');
                        }
                        var displayName = getClientDisplayName(client);
                        var unreadBadge = client.unread_count > 0 && String(client.id) !== String(currentClientId)
                            ? '<span class="unread-badge" style="display:inline-block; margin-left: 6px;">' + client.unread_count + '</span>'
                            : '';
                        var timeStr = client.last_message_at
                            ? new Date(client.last_message_at).toLocaleString()
                            : (lastMessage ? new Date(lastMessage.created_at).toLocaleString() : '');

                        var isActive = String(client.id) === String(currentClientId) ? ' active' : '';
                        html += '<div class="chat-item' + isActive + '" data-client-id="' + client.id + '">';
                        html += '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 4px;">';
                        html += '<div style="font-size: 13px; font-weight: 600;">' + escHtml(displayName || '') + '</div>';
                        html += unreadBadge;
                        html += '</div>';
                        html += '<div style="font-size: 12px; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' + escHtml(preview) + '</div>';
                        if (timeStr) {
                            html += '<div style="font-size: 11px; color: var(--text-secondary); margin-top: 4px;">' + timeStr + '</div>';
                        }
                        html += '</div>';
                    });
                    chatList.innerHTML = html;

                    document.querySelectorAll('.chat-item').forEach(function(item) {
                        item.addEventListener('click', function() {
                            document.querySelectorAll('.chat-item').forEach(function(el) {
                                el.classList.remove('active');
                            });
                            this.classList.add('active');

                            currentClientId = this.dataset.clientId;
                            // Show contact name in message header
                            var nameEl = this.querySelector('div div');
                            var contactName = nameEl ? nameEl.textContent : this.textContent;
                            document.getElementById('message-title').textContent = contactName;
                            loadMessages(currentAccountId, currentClientId);
                        });
                    });
                })
                .catch(function(err) {
                    console.error('[loadChats] error:', err);
                    if (!silent) {
                        chatList.innerHTML = '<div class="empty-state"><p>Failed to load chats</p></div>';
                    }
                });
        }

        function loadMessages(accountId, clientId) {
            var messageList = document.getElementById('message-list');
            messageList.innerHTML = '<div class="empty-state"><p>Loading messages...</p></div>';

            fetch('/admin/accounts/' + accountId + '/chats/' + clientId + '/messages')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var messages = Array.isArray(data) ? data : (data.messages || []);
                    document.getElementById('message-subtitle').textContent = messages.length + ' messages';

                    if (messages.length === 0) {
                        messageList.innerHTML = '<div class="empty-state"><p>No messages yet</p></div>';
                        document.getElementById('message-input-area').style.display = 'flex';
                        return;
                    }

                    var html = '';
                    messages.forEach(function(msg) {
                        html += renderMessageBubble(msg);
                    });
                    messageList.innerHTML = html;
                    scrollToBottom();

                    document.getElementById('message-input-area').style.display = 'flex';
                })
                .catch(function(err) {
                    console.error('[loadMessages] error:', err);
                    messageList.innerHTML = '<div class="empty-state"><p>Failed to load messages</p></div>';
                });
        }

        function escHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatPhone(num) {
            var digits = (num || '').replace(/\D/g, '');
            if (digits.length >= 10) {
                return '+' + digits.replace(/(\d{2})(\d{3})(\d{4})(\d+)/, '$1 $2 $3 $4');
            }
            return num;
        }

        function formatBytes(bytes) {
            if (!bytes) return '';
            var units = ['B', 'KB', 'MB', 'GB'];
            var i = 0, n = bytes;
            while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
            return n.toFixed(n < 10 && i > 0 ? 1 : 0) + ' ' + units[i];
        }

        function previewText(body, type, mediaName) {
            if (body) return body;
            switch (type) {
                case 'image':    return '📷 Photo';
                case 'video':    return '🎬 Video';
                case 'audio':    return '🎵 Audio';
                case 'sticker':  return '🎨 Sticker';
                case 'document': return '📄 ' + (mediaName || 'Document');
                default:         return '';
            }
        }

        function escAttr(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function renderMedia(msg) {
            var type = msg.message_type || 'text';
            if (type === 'text' || !msg.media_url) return '';

            var url = escHtml(msg.media_url);
            var urlAttr = escAttr(msg.media_url);
            var name = escHtml(msg.media_name || 'file');
            var common = 'display:block;max-width:220px;max-height:240px;border-radius:8px;margin-bottom:6px;';

            if (type === 'image' || type === 'sticker') {
                var size = type === 'sticker' ? 'max-width:100px;max-height:100px;' : '';
                return '<img src="' + url + '" alt="' + name + '" loading="lazy" style="' + common + size +
                    'cursor:pointer;" onclick="openLightbox(\'' + urlAttr + '\',\'image\')">';
            }
            if (type === 'video') {
                return '<video src="' + url + '" controls preload="metadata" style="' + common + '"></video>';
            }
            if (type === 'audio') {
                return '<audio src="' + url + '" controls preload="metadata" style="display:block;width:200px;margin-bottom:6px;"></audio>';
            }
            return '<a href="' + url + '" download="' + name + '" target="_blank" rel="noopener" ' +
                'style="display:flex;align-items:center;gap:10px;padding:9px 11px;background:rgba(0,0,0,0.2);' +
                'border-radius:8px;text-decoration:none;color:inherit;margin-bottom:6px;min-width:150px;">' +
                '<span style="font-size:22px;">📄</span>' +
                '<span style="min-width:0;">' +
                    '<span style="display:block;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + name + '</span>' +
                    '<span style="display:block;font-size:11px;opacity:0.7;">' + formatBytes(msg.media_size) + '</span>' +
                '</span></a>';
        }

        function renderMessageBubble(msg) {
            var isStaff = msg.sender_type === 'staff';
            var isSystem = msg.sender_type === 'system';
            var align = isStaff || isSystem ? 'flex-end' : 'flex-start';
            var bg = isStaff ? 'var(--accent)' : 'var(--bg-card)';
            var color = isStaff ? '#fff' : 'var(--text-primary)';
            var radius = isStaff ? '12px 12px 4px 12px' : '12px 12px 12px 4px';
            var border = isStaff ? 'none' : '1px solid var(--border)';
            var body = msg.message_body || '';

            return '<div style="display: flex; justify-content: ' + align + ';">' +
                '<div style="max-width: 75%; background-color: ' + bg + '; color: ' + color + '; padding: 10px 14px; border-radius: ' + radius + '; border: ' + border + '; font-size: 14px; word-wrap: break-word;">' +
                    renderMedia(msg) +
                    (body ? escHtml(body).replace(/\n/g, '<br>') : '') +
                    '<div style="font-size: 10px; opacity: 0.7; margin-top: 4px;">' + new Date(msg.created_at).toLocaleTimeString() + '</div>' +
                '</div>' +
            '</div>';
        }

        window.openLightbox = function(url, type) {
            var box = document.getElementById('admin-lightbox');
            document.getElementById('admin-lb-content').innerHTML = type === 'video'
                ? '<video src="' + url + '" controls autoplay style="max-width:92vw;max-height:88vh;"></video>'
                : '<img src="' + url + '" style="max-width:92vw;max-height:88vh;border-radius:6px;">';
            box.style.display = 'flex';
        };

        window.closeLightbox = function() {
            document.getElementById('admin-lightbox').style.display = 'none';
            document.getElementById('admin-lb-content').innerHTML = '';
        };

        function scrollToBottom() {
            var list = document.getElementById('message-list');
            list.scrollTop = list.scrollHeight;
        }

        // ─────────── Attachments ───────────
        var ADMIN_MAX_BYTES = 64 * 1024 * 1024;
        var adminPendingFile = null;

        window.adminOnFilePicked = function(e) {
            var file = e.target.files && e.target.files[0];
            if (!file) return;

            if (file.size > ADMIN_MAX_BYTES) {
                alert('File is too large (' + formatBytes(file.size) + '). Maximum is 64 MB.');
                e.target.value = '';
                return;
            }

            adminPendingFile = file;
            document.getElementById('admin-ap-name').textContent = file.name;
            document.getElementById('admin-ap-size').textContent = formatBytes(file.size);
            document.getElementById('admin-ap-icon').textContent =
                file.type.indexOf('image/') === 0 ? '🖼️' :
                file.type.indexOf('video/') === 0 ? '🎬' :
                file.type.indexOf('audio/') === 0 ? '🎵' : '📄';
            document.getElementById('admin-attach-preview').style.display = 'flex';
        };

        window.adminClearAttachment = function() {
            adminPendingFile = null;
            document.getElementById('admin-file-input').value = '';
            document.getElementById('admin-attach-preview').style.display = 'none';
        };

        document.getElementById('reply-form').addEventListener('submit', function(e) {
            e.preventDefault();
            var textarea = document.getElementById('reply-text');
            var text = textarea.value.trim();
            var file = adminPendingFile;

            if ((!text && !file) || !currentAccountId || !currentClientId) return;

            var body = new FormData();
            if (text) body.append('message', text);
            if (file) body.append('attachment', file);

            fetch('/admin/accounts/' + currentAccountId + '/chats/' + currentClientId + '/send', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: body,
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.message) {
                    document.getElementById('message-list')
                        .insertAdjacentHTML('beforeend', renderMessageBubble(data.message));
                    scrollToBottom();
                    textarea.value = '';
                    adminClearAttachment();
                }
                if (!data.success && data.error) {
                    alert(data.error);
                }
            })
            .catch(function(err) {
                console.error('[admin send] failed:', err);
                alert('Send failed. Check the engine is running.');
            });
        });

        (function initAdminChatRealtime() {
            var attempts = 0;
            (function tryInit() {
                if (typeof window.Echo === 'undefined' || !window.Echo.channel) {
                    if (++attempts <= 50) { setTimeout(tryInit, 100); return; }
                    console.error('[Admin Chat] Echo not loaded after 5s');
                    return;
                }

                window.Echo.channel('admin-events').listen('.NewMessageReceived', function(e) {
                    var message = e.message;
                    if (!message) return;

                    var isCurrent = currentAccountId && String(message.account_id) === String(currentAccountId)
                        && currentClientId && String(message.client_id) === String(currentClientId);

                    if (currentAccountId && String(message.account_id) === String(currentAccountId)) {
                        if (currentClientId && String(message.client_id) === String(currentClientId)) {
                            var messageList = document.getElementById('message-list');
                            if (messageList.querySelector('.empty-state')) {
                                messageList.innerHTML = '';
                            }
                            messageList.insertAdjacentHTML('beforeend', renderMessageBubble(message));
                            scrollToBottom();
                        }
                        loadChats(currentAccountId, true);
                    } else {
                        var badge = document.getElementById('unread-badge-' + message.account_id);
                        if (badge) {
                            badge.style.display = 'inline-block';
                            badge.textContent = parseInt(badge.textContent || '0') + 1;
                        }
                    }

                    if (!isCurrent && message.sender_type === 'client') {
                        showAdminRealtimeToast(message);
                    }
                });
            })();
        })();

        function showAdminRealtimeToast(msg) {
            var container = document.getElementById('admin-toast-container');
            if (!container || !msg) return;

            var clientObj = msg.client || {};
            var acctObj = msg.account || {};
            var displayName = getClientDisplayName(clientObj) || (clientObj.first_name || 'Client #' + msg.client_id);
            var initial = (displayName || '?')[0].toUpperCase();
            var platform = (acctObj.platform || '').toLowerCase();
            var isTg = platform.includes('tg') || platform.includes('telegram');
            var acctName = acctObj.account_name || acctObj.phone_number || ('Account #' + msg.account_id);
            var preview = (msg.message_type && msg.message_type !== 'text')
                ? previewText(msg.message_body, msg.message_type, msg.media_name)
                : (msg.message_body || 'New message');

            var toast = document.createElement('div');
            toast.className = 'admin-toast ' + (isTg ? 'tg-theme' : 'wa-theme');
            toast.innerHTML = '<div class="admin-toast-top">'
                + '<div class="admin-toast-user">'
                + '<div class="admin-toast-avatar" style="background:' + (isTg ? '#229ED9' : '#25D366') + '">' + escHtml(initial) + '</div>'
                + '<div class="admin-toast-info">'
                + '<div class="admin-toast-name">' + escHtml(displayName) + '</div>'
                + '<div class="admin-toast-account">' + escHtml(acctName) + ' • ' + (isTg ? 'Telegram' : 'WhatsApp') + '</div>'
                + '</div></div>'
                + '<div style="display:flex;align-items:center;gap:6px;">'
                + '<span style="font-size:11px;color:#888;">Just now</span>'
                + '<button class="admin-toast-close" onclick="dismissAdminToast(event, this)">✕</button>'
                + '</div></div>'
                + '<div class="admin-toast-body">' + escHtml(preview) + '</div>'
                + '<div class="admin-toast-footer">Click to view chat →</div>'
                + '<div class="admin-toast-progress" style="background:' + (isTg ? '#229ED9' : '#25D366') + '"></div>';

            toast.addEventListener('click', function(e) {
                if (e.target.closest('.admin-toast-close')) return;
                dismissAdminToastEl(toast);
                navigateToAdminChat(msg.account_id, msg.client_id, displayName);
            });

            var timer = setTimeout(function() { dismissAdminToastEl(toast); }, 7000);
            toast._timer = timer;

            while (container.children.length >= 4) {
                dismissAdminToastEl(container.firstElementChild);
            }
            container.appendChild(toast);
        }

        window.dismissAdminToast = function(event, btn) {
            if (event) { event.stopPropagation(); event.preventDefault(); }
            var toast = btn.closest('.admin-toast');
            if (toast) dismissAdminToastEl(toast);
        };

        function dismissAdminToastEl(toast) {
            if (!toast || toast._dismissing) return;
            toast._dismissing = true;
            if (toast._timer) clearTimeout(toast._timer);
            toast.classList.add('hiding');
            setTimeout(function() {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 260);
        }

        window.navigateToAdminChat = function(accountId, clientId, clientDisplayName) {
            if (!accountId || !clientId) return;
            var acctItem = document.querySelector('.account-item[data-account-id="' + accountId + '"]');
            if (acctItem) {
                document.querySelectorAll('.account-item').forEach(function(el) { el.classList.remove('active'); });
                acctItem.classList.add('active');
            }
            currentAccountId = accountId;
            currentClientId = clientId;

            document.getElementById('message-title').textContent = clientDisplayName || 'Chat';
            loadMessages(accountId, clientId);
            loadChats(accountId, true);
        };
    </script>

    <!-- Realtime Toast Notifications Container -->
    <div id="admin-toast-container" class="admin-toast-container"></div>

    <!-- Full-screen media viewer -->
    <div id="admin-lightbox" onclick="closeLightbox()"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.92); align-items:center; justify-content:center; z-index:9999;">
        <button onclick="closeLightbox()" style="position:absolute; top:16px; right:24px; font-size:32px; color:#fff; background:none; border:none; cursor:pointer; line-height:1;">×</button>
        <div id="admin-lb-content"></div>
    </div>
@endsection
