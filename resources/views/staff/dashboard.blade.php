@extends('layouts.staff')

@section('title', session('channel') === 'telegram' ? 'TG Cloud' : 'WP Cloud')
@section('page-title', session('channel') === 'telegram' ? 'TG Cloud' : 'WP Cloud')

@section('content')

@php
    $isTelegram = session('channel') === 'telegram';
    $platformColor = $isTelegram ? '#2AABEE' : '#25D366';
    $platformColorDark = $isTelegram ? '#1a8cc7' : '#128C7E';
    $platformBg = $isTelegram ? '#17212B' : '#111B21';
    $sidebarBg = $isTelegram ? '#17212B' : '#111B21';
    $chatBg = $isTelegram ? '#0E1621' : '#0B141A';
    $inputBg = $isTelegram ? '#17212B' : '#202C33';
    $bubbleOut = $isTelegram ? '#2B5278' : '#005C4B';
    $bubbleIn = $isTelegram ? '#182533' : '#202C33';
    $headerBg = $isTelegram ? '#17212B' : '#202C33';
    $platformName = $isTelegram ? 'Telegram' : 'WhatsApp';
@endphp

<style>
    /* ═══════════════════════════════════════
       RESET & BASE — platform-aware variables
    ═══════════════════════════════════════ */
    :root {
        --platform-color:      {{ $platformColor }};
        --platform-dark:       {{ $platformColorDark }};
        --platform-bg:         {{ $platformBg }};
        --sidebar-bg:          {{ $sidebarBg }};
        --chat-bg:             {{ $chatBg }};
        --input-bg:            {{ $inputBg }};
        --bubble-out:          {{ $bubbleOut }};
        --bubble-in:           {{ $bubbleIn }};
        --header-bg:           {{ $headerBg }};
        --text-primary:        #E9EDEF;
        --text-secondary:      #8696A0;
        --text-muted:          #667781;
        --border-subtle:       rgba(255,255,255,0.08);
        --hover-bg:            rgba(255,255,255,0.05);
        --active-bg:           rgba(255,255,255,0.08);
        --unread-badge:        var(--platform-color);
        --search-bg:           rgba(0,0,0,0.25);
        --tick-delivered:      #8696A0;
        --tick-read:           var(--platform-color);
        --chat-wallpaper: {{ $isTelegram ? 'none' : 'url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.02\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")' }};
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    .crm-shell {
        display: flex;
        height: calc(100vh - 64px); /* subtract header height */
        background: var(--chat-bg);
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        overflow: hidden;
    }

    /* ═══════════════════════════════════════
       DASHBOARD NAV — auto-collapsing icon sidebar
    ═══════════════════════════════════════ */
    .dashboard-nav {
        width: 56px;
        background: var(--header-bg);
        border-right: 1px solid var(--border-subtle);
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 12px 0;
        gap: 4px;
        flex-shrink: 0;
        transition: width .25s ease;
        overflow: hidden;
    }
    .dashboard-nav:hover {
        width: 130px;
        align-items: stretch;
        padding: 12px 8px;
    }

    .dash-nav-item {
        width: 100%;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0;
        cursor: pointer;
        color: var(--text-secondary);
        font-size: 20px;
        transition: all .15s;
        background: transparent;
        border: none;
        white-space: nowrap;
        overflow: hidden;
    }
    .dashboard-nav:hover .dash-nav-item {
        justify-content: flex-start;
        gap: 10px;
        padding: 0 12px;
    }
    .dash-nav-item:hover { background: var(--hover-bg); color: var(--text-primary); }
    .dash-nav-item.active { background: var(--active-bg); color: var(--platform-color); }

    .dash-nav-item .nav-label {
        font-size: 12px;
        font-weight: 600;
        opacity: 0;
        transition: opacity .15s ease .1s;
        white-space: nowrap;
    }
    .dashboard-nav:hover .nav-label { opacity: 1; }

    /* ═══════════════════════════════════════
       MIDDLE PANEL — Account list OR Chat list
    ═══════════════════════════════════════ */
    .middle-panel {
        width: 320px;
        min-width: 280px;
        max-width: 380px;
        background: var(--sidebar-bg);
        border-right: 1px solid var(--border-subtle);
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
        overflow: hidden;
    }

    /* ── (legacy .account-tabs / .account-tab / .tab-close removed — now using .account-section) ── */

    /* Search bar */
    .search-bar {
        padding: 8px 12px;
        background: var(--sidebar-bg);
        border-bottom: 1px solid var(--border-subtle);
    }
    .search-bar input {
        width: 100%;
        background: var(--search-bg);
        border: none;
        border-radius: 8px;
        padding: 9px 14px 9px 38px;
        color: var(--text-primary);
        font-size: 14px;
        outline: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%238696A0' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.099zm-5.44 1.398a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: 12px center;
    }
    .search-bar input::placeholder { color: var(--text-secondary); }

    /* Filter tabs */
    .filter-tab {
        flex: 1;
        padding: 6px 10px;
        border-radius: 8px;
        border: 1px solid transparent;
        background: transparent;
        color: var(--text-secondary);
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all .15s;
        text-align: center;
    }
    .filter-tab.active {
        background: var(--active-bg);
        color: var(--text-primary);
        border-color: var(--platform-color);
    }
    .filter-tab:hover { color: var(--text-primary); }

    /* ═══════════════════════════════════════
       ACCOUNT LIST SECTION — distinct panel
    ═══════════════════════════════════════ */
    .account-section {
        background: var(--header-bg);
        border-bottom: 2px solid var(--border-subtle);
        display: flex;
        flex-direction: column;
        max-height: 45%;
        transition: max-height .25s ease;
    }
    .account-section.collapsed { max-height: 44px; }

    .account-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        cursor: pointer;
        user-select: none;
        flex-shrink: 0;
    }
    .account-section-header:hover { background: var(--hover-bg); }
    .account-section-title {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--platform-color);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .account-section-title .account-count {
        background: var(--platform-color);
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        padding: 1px 7px;
        border-radius: 10px;
    }
    .account-section-chevron {
        color: var(--text-secondary);
        font-size: 14px;
        transition: transform .25s ease;
    }
    .account-section.collapsed .account-section-chevron { transform: rotate(-90deg); }

    .account-section-list {
        overflow-y: auto;
        padding: 4px 8px 8px;
        scrollbar-width: thin;
        scrollbar-color: rgba(255,255,255,0.1) transparent;
    }
    .account-section.collapsed .account-section-list { display: none; }
    .account-section-list::-webkit-scrollbar { width: 4px; }
    .account-section-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 2px; }

    .account-list-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 10px;
        border-radius: 10px;
        cursor: pointer;
        transition: background .15s;
        margin-bottom: 2px;
    }
    .account-list-item:hover { background: var(--hover-bg); }
    .account-list-item.active {
        background: var(--active-bg);
        box-shadow: inset 2px 0 0 var(--platform-color);
    }
    .account-list-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--platform-color), var(--platform-dark));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 700;
        color: #fff;
        flex-shrink: 0;
    }
    .account-list-info { flex: 1; min-width: 0; }
    .account-list-name {
        font-size: 13.5px;
        font-weight: 600;
        color: var(--text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .account-list-phone {
        font-size: 11px;
        color: var(--text-secondary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-top: 1px;
    }
    .account-list-badge {
        min-width: 18px;
        height: 18px;
        border-radius: 9px;
        background: var(--platform-color);
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 5px;
        flex-shrink: 0;
    }

    /* ═══════════════════════════════════════
       CHAT LIST SECTION — distinct panel
    ═══════════════════════════════════════ */
    .chat-section {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-width: 0;
        overflow: hidden;
    }
    .chat-section-header {
        padding: 10px 14px 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
    }
    .chat-section-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
    }
    .chat-section-subtitle {
        font-size: 11px;
        color: var(--text-secondary);
        margin-top: 1px;
    }
    .chat-section-header .chat-count {
        font-size: 11px;
        color: var(--text-secondary);
        background: var(--active-bg);
        padding: 2px 10px;
        border-radius: 10px;
    }

    /* Collapsible account nav (legacy — kept for compatibility) */
    .nav-toggle { display: none; }
    .account-nav-item { padding: 6px 4px; border-radius: 8px; cursor: pointer; transition: background .15s; }
    .account-nav-item:hover { background: var(--hover-bg); }
    .account-nav-main { display: flex; align-items: center; }
    .account-nav-avatar {
        width: 28px; height: 28px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 700; color: #fff; flex-shrink: 0;
    }
    .account-nav-access { margin-top: 6px; padding-bottom: 4px; border-bottom: 1px solid var(--border-subtle); }
    .account-nav-item.open .nav-chevron { transform: rotate(90deg); }
    .account-nav-item.open .account-nav-access { display: block; }
    .nav-unread {
        min-width: 18px; height: 18px; border-radius: 9px;
        background: var(--unread-badge); color: #fff; font-size: 10px; font-weight: 700;
        display: inline-flex; align-items: center; justify-content: center; padding: 0 5px; margin-left: 6px;
    }
    .account-nav-access { animation: slideDown .18s ease; }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-4px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Contact list */
    .contact-list {
        flex: 1;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: rgba(255,255,255,0.1) transparent;
    }
    .contact-list::-webkit-scrollbar { width: 4px; }
    .contact-list::-webkit-scrollbar-track { background: transparent; }
    .contact-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 2px; }

    .contact-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        cursor: pointer;
        transition: background 0.1s;
        border-bottom: 1px solid var(--border-subtle);
        position: relative;
    }
    .contact-item:hover { background: var(--hover-bg); }
    .contact-item.active { background: var(--active-bg); }

    .contact-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--platform-color) 0%, var(--platform-dark) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        flex-shrink: 0;
        text-transform: uppercase;
    }

    .contact-info { flex: 1; min-width: 0; }
    .contact-row1 {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        margin-bottom: 3px;
    }
    .contact-name {
        font-size: 15px;
        font-weight: 500;
        color: var(--text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 200px;
    }
    .contact-time {
        font-size: 11px;
        color: var(--text-secondary);
        flex-shrink: 0;
        margin-left: 8px;
    }
    .contact-time.unread-time { color: var(--platform-color); }

    .contact-row2 {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .contact-preview {
        font-size: 13px;
        color: var(--text-secondary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 240px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .contact-preview .tick { font-size: 12px; }
    .tick-sent    { color: var(--tick-delivered); }
    .tick-read    { color: var(--tick-read); }

    .unread-badge {
        min-width: 20px;
        height: 20px;
        border-radius: 10px;
        background: var(--platform-color);
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 5px;
        flex-shrink: 0;
    }

    .contact-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 200px;
        color: var(--text-secondary);
        gap: 10px;
    }
    .contact-empty .icon { font-size: 40px; opacity: 0.4; }
    .contact-empty p { font-size: 13px; }

    /* ═══════════════════════════════════════
       RIGHT PANEL — Chat window
    ═══════════════════════════════════════ */
    .right-panel {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: var(--chat-bg);
        position: relative;
        overflow: hidden;
    }

    /* Chat wallpaper pattern */
    .right-panel::before {
        content: '';
        position: absolute;
        inset: 0;
        background-image: var(--chat-wallpaper);
        background-size: 50px 50px;
        pointer-events: none;
        z-index: 0;
    }

    /* Chat header */
    .chat-header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        background: var(--header-bg);
        border-bottom: 1px solid var(--border-subtle);
        min-height: 60px;
        z-index: 2;
        position: relative;
    }
    .chat-header-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--platform-color), var(--platform-dark));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-weight: 700;
        color: #fff;
        text-transform: uppercase;
        flex-shrink: 0;
    }
    .chat-header-info { flex: 1; }
    .chat-header-name {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
        line-height: 1.2;
    }
    .chat-header-status {
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 2px;
    }
    .chat-header-status.online { color: var(--platform-color); }

    .chat-header-actions {
        display: flex;
        gap: 4px;
    }
    .header-btn {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: transparent;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-secondary);
        transition: background 0.15s, color 0.15s, opacity 0.15s;
        font-size: 18px;
    }
    .header-btn:hover { background: var(--hover-bg); color: var(--text-primary); }
    .header-btn.sound-muted { opacity: 0.45; filter: grayscale(1); }

    /* Welcome screen */
    .chat-welcome {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: var(--text-secondary);
        gap: 16px;
        z-index: 1;
        position: relative;
    }
    .chat-welcome .welcome-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: rgba(255,255,255,0.05);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 36px;
        margin-bottom: 8px;
    }
    .chat-welcome h3 { font-size: 20px; font-weight: 300; color: var(--text-primary); }
    .chat-welcome p { font-size: 13px; max-width: 280px; text-align: center; line-height: 1.5; }

    /* Messages area */
    .messages-area {
        flex: 1;
        overflow-y: auto;
        padding: 16px 12%;
        display: flex;
        flex-direction: column;
        gap: 2px;
        z-index: 1;
        position: relative;
        scrollbar-width: thin;
        scrollbar-color: rgba(255,255,255,0.1) transparent;
    }
    .messages-area::-webkit-scrollbar { width: 5px; }
    .messages-area::-webkit-scrollbar-track { background: transparent; }
    .messages-area::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 3px; }

    /* Date separator */
    .date-separator {
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 12px 0;
    }
    .date-separator span {
        background: rgba(17,27,33,0.8);
        color: var(--text-secondary);
        font-size: 11.5px;
        padding: 5px 12px;
        border-radius: 8px;
        backdrop-filter: blur(4px);
    }

    /* Message bubble */
    .msg-row {
        display: flex;
        margin-bottom: 2px;
    }
    .msg-row.out { justify-content: flex-end; }
    .msg-row.in  { justify-content: flex-start; }
    .msg-row.system { justify-content: center; }

    /* Group consecutive same-sender bubbles */
    .msg-row.in  + .msg-row.in  .bubble  { border-top-left-radius: 4px; }
    .msg-row.out + .msg-row.out .bubble  { border-top-right-radius: 4px; }

    .bubble {
        max-width: 65%;
        padding: 7px 10px 7px 10px;
        border-radius: 8px;
        position: relative;
        word-break: break-word;
        line-height: 1.45;
    }
    .msg-row.out .bubble {
        background: var(--bubble-out);
        border-radius: 8px 2px 8px 8px;
        color: #E9EDEF;
    }
    .msg-row.in .bubble {
        background: var(--bubble-in);
        border-radius: 2px 8px 8px 8px;
        color: #E9EDEF;
    }
    .msg-row.system .bubble {
        background: rgba(17,27,33,0.7);
        border-radius: 8px;
        color: var(--text-secondary);
        font-size: 12px;
        padding: 5px 12px;
    }

    .bubble-text {
        font-size: 14.2px;
        padding-right: 52px; /* space for timestamp+ticks */
    }

    .bubble-meta {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 4px;
        margin-top: 2px;
        float: right;
        clear: both;
        margin-left: 8px;
        margin-bottom: -2px;
        position: relative;
        bottom: 0;
    }
    .bubble-time {
        font-size: 11px;
        color: rgba(233,237,239,0.55);
        white-space: nowrap;
    }
    .bubble-ticks {
        font-size: 13px;
        line-height: 1;
    }
    .bubble-ticks.sent     { color: rgba(233,237,239,0.55); }
    .bubble-ticks.read     { color: var(--platform-color); }

    /* Scroll to bottom btn */
    .scroll-to-bottom {
        position: absolute;
        bottom: 80px;
        right: calc(12% - 20px);
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: var(--header-bg);
        border: 1px solid var(--border-subtle);
        color: var(--text-primary);
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10;
        box-shadow: 0 2px 12px rgba(0,0,0,0.4);
        transition: all 0.15s;
        display: none;
    }
    .scroll-to-bottom:hover { background: var(--active-bg); }
    .scroll-to-bottom .unread-pill {
        position: absolute;
        top: -6px;
        right: -4px;
        background: var(--platform-color);
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        padding: 1px 5px;
        border-radius: 8px;
        min-width: 18px;
        text-align: center;
    }

    /* ═══════════════════════════════════════
       INPUT BAR
    ═══════════════════════════════════════ */
    .input-bar {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        padding: 10px 16px;
        background: var(--input-bg);
        border-top: 1px solid var(--border-subtle);
        z-index: 2;
        position: relative;
    }

    .input-bar-actions {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .input-action-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: transparent;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-secondary);
        font-size: 20px;
        transition: color 0.15s;
        flex-shrink: 0;
    }
    .input-action-btn:hover { color: var(--text-primary); }

    .message-composer {
        flex: 1;
        background: var(--search-bg);
        border-radius: 10px;
        padding: 10px 14px;
        min-height: 42px;
        max-height: 160px;
        overflow-y: auto;
        color: var(--text-primary);
        font-size: 14.5px;
        line-height: 1.45;
        outline: none;
        cursor: text;
        scrollbar-width: none;
        border: none;
        resize: none;
        font-family: inherit;
    }
    .message-composer:empty::before {
        content: attr(data-placeholder);
        color: var(--text-secondary);
        pointer-events: none;
    }
    .message-composer::-webkit-scrollbar { display: none; }

    .send-btn {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: var(--platform-color);
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 18px;
        flex-shrink: 0;
        transition: background 0.15s, transform 0.1s;
    }
    .send-btn:hover { background: var(--platform-dark); }
    .send-btn:active { transform: scale(0.93); }

    /* ═══════════ Media in bubbles ═══════════ */
    .bubble-media { margin: -2px 0 4px; border-radius: 8px; overflow: hidden; }
    .bubble-media img,
    .bubble-media video {
        display: block;
        max-width: 280px;
        max-height: 320px;
        width: auto;
        height: auto;
        border-radius: 8px;
        cursor: pointer;
        background: rgba(0,0,0,0.2);
    }
    .bubble-media.sticker img { max-width: 140px; max-height: 140px; background: transparent; }
    .bubble-media audio { display: block; width: 260px; max-width: 100%; }

    .doc-chip {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        background: rgba(0,0,0,0.18);
        border-radius: 8px;
        text-decoration: none;
        color: inherit;
        min-width: 190px;
        max-width: 280px;
    }
    .doc-chip:hover { background: rgba(0,0,0,0.28); }
    .doc-chip .doc-icon { font-size: 26px; flex-shrink: 0; }
    .doc-chip .doc-meta { min-width: 0; }
    .doc-chip .doc-name {
        font-size: 13.5px;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .doc-chip .doc-size { font-size: 11.5px; color: var(--text-secondary); margin-top: 2px; }

    /* Upload progress shade over an optimistic bubble */
    .bubble.uploading { opacity: 0.62; }

    /* ═══════════ Attach menu ═══════════ */
    .attach-wrap { position: relative; }
    .attach-menu {
        position: absolute;
        bottom: 52px;
        left: 0;
        background: var(--bg-card, #242424);
        border: 1px solid var(--border, #333);
        border-radius: 12px;
        padding: 6px;
        display: none;
        flex-direction: column;
        gap: 2px;
        min-width: 190px;
        box-shadow: 0 8px 28px rgba(0,0,0,0.5);
        z-index: 60;
    }
    .attach-menu.open { display: flex; }
    .attach-menu button {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 10px 12px;
        background: transparent;
        border: none;
        border-radius: 8px;
        color: var(--text-primary);
        font-size: 14px;
        cursor: pointer;
        text-align: left;
        font-family: inherit;
    }
    .attach-menu button:hover { background: rgba(255,255,255,0.07); }
    .attach-menu .ico { font-size: 18px; width: 22px; text-align: center; }

    /* ═══════════ Pending attachment preview ═══════════ */
    .attach-preview {
        display: none;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        margin: 0 12px 8px;
        background: var(--search-bg, #2a2a2a);
        border-radius: 10px;
        border: 1px solid var(--border, #333);
    }
    .attach-preview.open { display: flex; }
    .attach-preview img,
    .attach-preview video {
        width: 52px;
        height: 52px;
        object-fit: cover;
        border-radius: 6px;
        flex-shrink: 0;
    }
    .attach-preview .ap-icon { font-size: 30px; width: 52px; text-align: center; flex-shrink: 0; }
    .attach-preview .ap-info { flex: 1; min-width: 0; }
    .attach-preview .ap-name {
        font-size: 13.5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .attach-preview .ap-size { font-size: 11.5px; color: var(--text-secondary); margin-top: 2px; }
    .attach-preview .ap-remove {
        background: transparent;
        border: none;
        color: var(--text-secondary);
        font-size: 22px;
        cursor: pointer;
        padding: 0 6px;
        line-height: 1;
    }
    .attach-preview .ap-remove:hover { color: var(--danger, #ef4444); }

    /* ═══════════ Lightbox ═══════════ */
    .lightbox {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.92);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }
    .lightbox.open { display: flex; }
    .lightbox img,
    .lightbox video { max-width: 92vw; max-height: 88vh; border-radius: 6px; }
    .lightbox .lb-close {
        position: absolute;
        top: 18px;
        right: 26px;
        font-size: 34px;
        color: #fff;
        background: transparent;
        border: none;
        cursor: pointer;
        line-height: 1;
    }
    .lightbox .lb-download {
        position: absolute;
        bottom: 22px;
        padding: 9px 20px;
        background: rgba(255,255,255,0.14);
        color: #fff;
        border-radius: 22px;
        text-decoration: none;
        font-size: 14px;
    }
    .lightbox .lb-download:hover { background: rgba(255,255,255,0.24); }

    /* Drag-and-drop overlay */
    .drop-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.72);
        border: 2px dashed var(--platform-color);
        border-radius: 10px;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        color: #fff;
        z-index: 55;
        pointer-events: none;
    }
    .drop-overlay.open { display: flex; }

    /* Loading skeleton */
    @keyframes shimmer {
        0%   { background-position: -400px 0; }
        100% { background-position: 400px 0; }
    }
    .skeleton {
        background: linear-gradient(90deg, var(--bubble-in) 25%, rgba(255,255,255,0.05) 50%, var(--bubble-in) 75%);
        background-size: 800px 100%;
        animation: shimmer 1.5s infinite;
        border-radius: 8px;
        height: 48px;
        margin-bottom: 8px;
    }

    /* Typing indicator */
    .typing-indicator {
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 8px 14px;
        background: var(--bubble-in);
        border-radius: 2px 12px 12px 12px;
        width: fit-content;
        margin-left: 0;
    }
    .typing-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--text-secondary);
        animation: typing-bounce 1.4s infinite ease-in-out;
    }
    .typing-dot:nth-child(1) { animation-delay: 0s; }
    .typing-dot:nth-child(2) { animation-delay: 0.2s; }
    .typing-dot:nth-child(3) { animation-delay: 0.4s; }
    @keyframes typing-bounce {
        0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
        30% { transform: translateY(-6px); opacity: 1; }
    }

    /* New message incoming flash */
    @keyframes msg-pop {
        from { opacity: 0; transform: translateY(8px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .msg-row.new { animation: msg-pop 0.2s ease; }

    /* Highlight a new client appearing in sidebar for the first time */
    @keyframes pulse-highlight {
        0%   { background: transparent; }
        30%  { background: rgba(59,130,246,0.35); }
        100% { background: transparent; }
    }

    /* No account selected */
    .no-account-selected {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: var(--text-secondary);
        font-size: 14px;
        text-align: center;
        flex-direction: column;
        gap: 10px;
    }

    /* ═══════════════════════════════════════
       RESPONSIVE
    ═══════════════════════════════════════ */
    @media (max-width: 768px) {
        .dashboard-nav {
            width: 100%;
            height: 56px;
            flex-direction: row;
            justify-content: center;
            border-right: none;
            border-bottom: 1px solid var(--border-subtle);
            order: -1;
        }
        .dashboard-nav:hover { width: 100%; padding: 0; align-items: center; }
        .dashboard-nav:hover .dash-nav-item { justify-content: center; padding: 0; gap: 0; }
        .dashboard-nav:hover .nav-label { display: none; }

        .middle-panel { width: 100%; max-width: 100%; }
        .right-panel { display: none; }
        .crm-shell.chat-open .dashboard-nav { display: none; }
        .crm-shell.chat-open .middle-panel { display: none; }
        .crm-shell.chat-open .right-panel { display: flex; }
        .messages-area { padding: 12px 4%; }
        .top-header { padding: 0 16px; }
        .content-area { padding: 16px; }
    }

    @media (max-width: 480px) {
        .account-list-item { padding: 8px; }
        .account-list-avatar { width: 32px; height: 32px; font-size: 12px; }
        .account-list-name { font-size: 12.5px; }
        .chat-header { padding: 8px 12px; min-height: 52px; }
        .messages-area { padding: 10px 3%; }
    }

    /* ═══════════════════════════════════════
       REALTIME TOAST POPUP NOTIFICATIONS
    ═══════════════════════════════════════ */
    .rt-toast-container {
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

    .rt-toast {
        pointer-events: auto;
        background: rgba(20, 27, 41, 0.96);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 14px;
        padding: 13px 15px 11px;
        box-shadow: 0 16px 36px -4px rgba(0, 0, 0, 0.65), 0 0 0 1px rgba(255, 255, 255, 0.06);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        cursor: pointer;
        position: relative;
        overflow: hidden;
        transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        animation: rtToastSlideIn 0.32s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        user-select: none;
    }

    .rt-toast.tg-theme { border-left: 4px solid #229ED9; }
    .rt-toast.wa-theme { border-left: 4px solid #25D366; }

    .rt-toast:hover {
        transform: translateY(-2px);
        background: rgba(26, 36, 54, 0.98);
        box-shadow: 0 20px 42px -4px rgba(0, 0, 0, 0.8), 0 0 16px rgba(0, 0, 0, 0.4);
    }

    .rt-toast:active { transform: scale(0.99); }
    .rt-toast.rt-toast-hiding { animation: rtToastSlideOut 0.25s cubic-bezier(0.4, 0, 1, 1) forwards; }

    @keyframes rtToastSlideIn {
        from { opacity: 0; transform: translateY(30px) scale(0.94); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    @keyframes rtToastSlideOut {
        from { opacity: 1; transform: translateY(0) scale(1); }
        to { opacity: 0; transform: translateY(20px) scale(0.92); }
    }

    .rt-toast-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .rt-toast-user {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
        flex: 1;
    }

    .rt-toast-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
        position: relative;
        flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }

    .rt-toast-platform-icon {
        position: absolute;
        bottom: -3px;
        right: -3px;
        font-size: 10px;
        background: #0f172a;
        border-radius: 50%;
        width: 16px;
        height: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1.5px solid #1e293b;
    }

    .rt-toast-info { min-width: 0; flex: 1; }

    .rt-toast-name {
        font-size: 13.5px;
        font-weight: 600;
        color: #ffffff;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.3;
    }

    .rt-toast-account {
        font-size: 11px;
        color: #94a3b8;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.3;
    }

    .rt-toast-controls {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
    }

    .rt-toast-time { font-size: 11px; color: #64748b; }

    .rt-toast-close {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        border: none;
        background: rgba(255, 255, 255, 0.08);
        color: #94a3b8;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        transition: background 0.15s, color 0.15s;
        padding: 0;
        line-height: 1;
    }

    .rt-toast-close:hover {
        background: rgba(239, 68, 68, 0.25);
        color: #ef4444;
    }

    .rt-toast-body {
        margin-top: 6px;
        padding-left: 46px;
        font-size: 12.5px;
        color: #cbd5e1;
        line-height: 1.4;
        word-break: break-word;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .rt-toast-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 5px;
        margin-top: 6px;
        padding-left: 46px;
        font-size: 11px;
        font-weight: 500;
    }

    .rt-toast.tg-theme .rt-toast-footer { color: #38bdf8; }
    .rt-toast.wa-theme .rt-toast-footer { color: #4ade80; }

    .rt-toast-arrow {
        width: 12px;
        height: 12px;
        transition: transform 0.15s ease;
    }

    .rt-toast:hover .rt-toast-arrow { transform: translateX(3px); }

    .rt-toast-progress-bar {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 2.5px;
        transform-origin: left;
        animation: rtToastProgress 7s linear forwards;
    }

    .rt-toast:hover .rt-toast-progress-bar { animation-play-state: paused; }

    @keyframes rtToastProgress {
        from { transform: scaleX(1); }
        to { transform: scaleX(0); }
    }

    @media (max-width: 640px) {
        .rt-toast-container {
            bottom: 72px;
            right: 12px;
            left: 12px;
            width: auto;
            max-width: none;
        }
    }
</style>

<div class="crm-shell" id="crmShell">

    {{-- ════════════════════════════════════
         DASHBOARD NAV — auto-collapsing icon sidebar
    ════════════════════════════════════ --}}
    <div class="dashboard-nav">
        <button class="dash-nav-item active" id="dashNavAccounts" onclick="showDashPanel('accounts')" title="Accounts">
            <span class="nav-icon">@if($isTelegram) ✈ @else 💬 @endif</span>
            <span class="nav-label">Accounts</span>
        </button>
        <button class="dash-nav-item" id="dashNavChats" onclick="showDashPanel('chats')" title="Chats" disabled style="opacity:0.35;cursor:not-allowed;">
            <span class="nav-icon">💬</span>
            <span class="nav-label">Chats</span>
        </button>
    </div>

    {{-- ════════════════════════════════════
         MIDDLE PANEL — accounts OR chats view
    ════════════════════════════════════ --}}
    <div class="middle-panel">

        {{-- Account list view --}}
        <div id="accountsView" style="display:flex;flex-direction:column;height:100%;overflow:hidden;">
            <div class="account-section" id="accountSection">
                <div class="account-section-header" onclick="toggleAccountSection()" title="Click to collapse/expand">
                    <span class="account-section-title">
                        @if($isTelegram) ✈ @else 💬 @endif Accounts
                        <span class="account-count" id="accountCount">{{ $accounts->count() }}</span>
                    </span>
                    <span class="account-section-chevron" id="accountSectionChevron">▾</span>
                </div>
                <div class="account-section-list" id="accountList">
                    @forelse($accounts as $account)
                        <div
                            class="account-list-item {{ $loop->first ? 'active' : '' }}"
                            data-account-id="{{ $account->id }}"
                            data-account-name="{{ $account->account_name }}"
                            data-account-phone="{{ $account->phone_number }}"
                            data-account-platform="{{ $account->platform }}"
                            onclick="selectAccount(this)"
                            title="{{ $account->phone_number }}"
                        >
                            <div class="account-list-avatar">
                                {{ strtoupper(substr($account->account_name, 0, 1)) }}
                            </div>
                            <div class="account-list-info">
                                <div class="account-list-name">{{ $account->account_name }}</div>
                                <div class="account-list-phone">{{ $account->phone_number }}</div>
                            </div>
                            @if($account->unread_count > 0)
                                <span class="account-list-badge" id="tab-unread-{{ $account->id }}">{{ $account->unread_count }}</span>
                            @else
                                <span class="account-list-badge" id="tab-unread-{{ $account->id }}" style="display:none;">0</span>
                            @endif
                        </div>
                    @empty
                        <div style="padding:12px 10px;color:var(--text-secondary);font-size:13px;">No accounts assigned</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Chat list view (hidden until account selected) --}}
        <div id="chatsView" style="display:none;flex-direction:column;height:100%;overflow:hidden;">
            <div class="chat-section-header">
                <div>
                    <div class="chat-section-title" id="chatSectionTitle">Conversations</div>
                    <div class="chat-section-subtitle" id="chatSectionSubtitle">Select an account first</div>
                </div>
                <span class="chat-count" id="chatCount" style="display:none;">0</span>
            </div>

            {{-- Search --}}
            <div class="search-bar">
                <input
                    type="text"
                    id="contactSearch"
                    placeholder="Search conversations…"
                    oninput="filterContacts(this.value)"
                    autocomplete="off"
                >
            </div>

            {{-- Filter tabs: All / Unread --}}
            <div style="display:flex;gap:6px;padding:6px 10px;border-bottom:1px solid var(--border-subtle);background:var(--header-bg);">
                <button class="filter-tab active" data-filter="all" onclick="setContactFilter('all', this)">All</button>
                <button class="filter-tab" data-filter="unread" onclick="setContactFilter('unread', this)">Unread</button>
            </div>

            {{-- Contact list --}}
            <div class="contact-list" id="contactList">
                <div class="contact-empty">
                    <div class="icon">
                        @if($isTelegram) ✈ @else 💬 @endif
                    </div>
                    <p>Select an account to view conversations</p>
                </div>
            </div>
        </div>

    </div>

    {{-- ════════════════════════════════════
         RIGHT PANEL
    ════════════════════════════════════ --}}
    <div class="right-panel" id="rightPanel">

        {{-- Contact Details Panel (hidden initially) --}}
        <div id="contactDetailsPanel" style="display:none;position:absolute;top:60px;right:0;width:280px;height:calc(100% - 60px);background:var(--header-bg);border-left:1px solid var(--border-subtle);z-index:50;overflow-y:auto;padding:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <span style="font-size:15px;font-weight:700;">Contact Info</span>
                <button onclick="hideContactDetails()" style="background:none;border:none;color:var(--text-secondary);font-size:20px;cursor:pointer;">✕</button>
            </div>
            <div style="text-align:center;margin-bottom:20px;">
                <div id="detail-avatar" style="width:80px;height:80px;border-radius:50%;background:var(--platform-color);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;color:#fff;margin:0 auto 12px;"></div>
                <div id="detail-name" style="font-size:18px;font-weight:700;color:var(--text-primary);"></div>
                <div id="detail-sub" style="font-size:13px;color:var(--text-secondary);margin-top:4px;"></div>
            </div>
            <div style="border-top:1px solid var(--border-subtle);padding-top:16px;">
                <div style="font-size:12px;color:var(--text-secondary);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Details</div>
                <div id="detail-identifier" style="font-size:14px;color:var(--text-primary);padding:8px 0;border-bottom:1px solid var(--border-subtle);"></div>
                <div id="detail-platform" style="font-size:14px;color:var(--text-primary);padding:8px 0;border-bottom:1px solid var(--border-subtle);"></div>
                <div id="detail-status" style="font-size:14px;color:var(--text-primary);padding:8px 0;"></div>
            </div>
        </div>

        {{-- Welcome / empty state --}}
        <div class="chat-welcome" id="chatWelcome">
            <div class="welcome-icon">
                @if($isTelegram) ✈ @else 💬 @endif
            </div>
            <h3>{{ $platformName }} CRM</h3>
            <p>Select a conversation from the left panel to start chatting</p>
            <p style="font-size:12px;margin-top:8px;opacity:0.5;">Your messages are end-to-end managed</p>
        </div>

        {{-- Chat view (hidden initially) --}}
        <div id="chatView" style="display:none;flex-direction:column;height:100%;">

            {{-- Chat Header --}}
            <div class="chat-header" id="chatHeader">
                <div class="chat-header-avatar" id="headerAvatar">?</div>
                <div class="chat-header-info">
                    <div class="chat-header-name" id="headerName"
                         onclick="showContactDetails()"
                         style="cursor:pointer;"
                         title="Click to view contact details">—</div>
                    <div class="chat-header-status" id="headerStatus">—</div>
                </div>
                <div class="chat-header-actions">
                    <button class="header-btn" id="soundToggleBtn" title="Notification Sound (Click to test/toggle)" onclick="toggleSound()">🔔</button>
                    <button class="header-btn" title="Search" onclick="toggleMsgSearch()">🔍</button>
                    <button class="header-btn" title="Switch channel" onclick="window.location.href='{{ route('staff.channel') }}'">⇄</button>
                </div>
            </div>

            {{-- Messages --}}
            <div class="messages-area" id="messagesArea" onscroll="handleScroll()">
                {{-- Loaded dynamically --}}
            </div>

            {{-- Drag-and-drop target --}}
            <div class="drop-overlay" id="dropOverlay">Drop file to send</div>

            {{-- Scroll to bottom --}}
            <button class="scroll-to-bottom" id="scrollBtn" onclick="scrollToBottom()">↓</button>

            {{-- Pending attachment preview --}}
            <div class="attach-preview" id="attachPreview">
                <div id="apThumb"></div>
                <div class="ap-info">
                    <div class="ap-name" id="apName"></div>
                    <div class="ap-size" id="apSize"></div>
                </div>
                <button class="ap-remove" onclick="clearAttachment()" title="Remove">&times;</button>
            </div>

            {{-- Input bar --}}
            <div class="input-bar" id="inputBar">
                <div class="input-bar-actions attach-wrap">
                    <button class="input-action-btn" title="Attach" onclick="toggleAttachMenu(event)">📎</button>
                    <div class="attach-menu" id="attachMenu">
                        <button onclick="pickFile('image/*')"><span class="ico">🖼️</span> Photo</button>
                        <button onclick="pickFile('video/*')"><span class="ico">🎬</span> Video</button>
                        <button onclick="pickFile('audio/*')"><span class="ico">🎵</span> Audio</button>
                        <button onclick="pickFile('')"><span class="ico">📄</span> Document</button>
                    </div>
                    <button class="input-action-btn" title="Emoji" onclick="toggleEmoji()">😊</button>
                </div>

                {{-- Single input reused for every attachment kind --}}
                <input type="file" id="fileInput" style="display:none" onchange="onFilePicked(event)">

                <div
                    class="message-composer"
                    id="msgComposer"
                    contenteditable="true"
                    data-placeholder="Message"
                    onkeydown="composerKeydown(event)"
                    oninput="composerInput()"
                ></div>
                <button class="send-btn" id="sendBtn" onclick="sendMessage()" title="Send">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M22 2L11 13" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M22 2L15 22 11 13 2 9l20-7z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>

        </div>

    </div>
</div>

{{-- Full-screen media viewer --}}
<div class="lightbox" id="lightbox" onclick="closeLightbox(event)">
    <button class="lb-close" onclick="closeLightbox(event, true)">&times;</button>
    <div id="lbContent"></div>
    <a class="lb-download" id="lbDownload" href="#" download>Download</a>
</div>

{{-- Audio notification --}}
<audio id="notify-sound" src="{{ asset('sounds/notify.wav') }}" preload="auto"></audio>

{{-- Realtime Notification Popup Toast Container --}}
<div id="rt-toast-container" class="rt-toast-container"></div>

{{-- Realtime connection status badge --}}
<div id="rt-status" style="position:fixed;bottom:12px;right:12px;z-index:9999;padding:6px 12px;border-radius:20px;font-size:12px;font-family:monospace;background:#333;color:#fff;display:flex;align-items:center;gap:6px;box-shadow:0 2px 8px rgba(0,0,0,.4)">
    <span id="rt-dot" style="width:8px;height:8px;border-radius:50%;background:#f59e0b"></span>
    <span id="rt-text">connecting…</span>
</div>

<script>
(function() {
    // ═══════════════════════ STATE ═══════════════════════
    const CSRF      = document.querySelector('meta[name="csrf-token"]').content;
    const STAFF_ID  = {{ Auth::id() }};
    const IS_TG     = {{ $isTelegram ? 'true' : 'false' }};

    // Realtime status badge updater
    function setRtState(state, label) {
        const dot = document.getElementById('rt-dot');
        const txt = document.getElementById('rt-text');
        if (!dot || !txt) return;
        txt.textContent = label;
        if (state === 'connected')   { dot.style.background = '#22c55e'; }
        else if (state === 'error')  { dot.style.background = '#ef4444'; }
        else                         { dot.style.background = '#f59e0b'; }
    }

    let currentAccountId   = null;
    let currentAccountName = '';
    let currentClientId    = null;
    let currentClientName  = '';
    let allContacts        = [];      // full list from server for search
    let newMsgCount        = 0;       // msgs received while scrolled up
    let isAtBottom         = true;
    let lastDateLabel      = null;

    // ═══════════════════════ ACCOUNT SECTION TOGGLE ═══════════════════════
    function toggleAccountSection() {
        const section = document.getElementById('accountSection');
        section.classList.toggle('collapsed');
        try { localStorage.setItem('staff_account_section_collapsed', section.classList.contains('collapsed') ? '1' : '0'); } catch (e) {}
    }

    // Restore account section collapsed state
    try {
        if (localStorage.getItem('staff_account_section_collapsed') === '1') {
            document.getElementById('accountSection')?.classList.add('collapsed');
        }
    } catch (e) {}

    // ═══════════════════════ ACCOUNT SELECTION ═══════════════════════
    window.closeAccountTab = function(btn, accountId) {
        const item = btn.closest('.account-list-item');
        const wasActive = item.classList.contains('active');
        item.remove();
        const count = document.querySelectorAll('.account-list-item').length;
        const countEl = document.getElementById('accountCount');
        if (countEl) countEl.textContent = count;

        if (wasActive) {
            const remaining = document.querySelectorAll('.account-list-item');
            if (remaining.length > 0) {
                selectAccount(remaining[0]);
            } else {
                currentAccountId = null;
                currentAccountName = '';
                document.getElementById('chatWelcome').style.display = 'flex';
                document.getElementById('chatView').style.display = 'none';
                document.getElementById('contactList').innerHTML = `
                    <div class="contact-empty">
                        <div class="icon">${IS_TG ? '✈' : '💬'}</div>
                        <p>No accounts assigned</p>
                    </div>`;
                updateChatSectionHeader(null);
            }
        }
    };

    window.selectAccount = function(item) {
        document.querySelectorAll('.account-list-item').forEach(t => t.classList.remove('active'));
        item.classList.add('active');

        currentAccountId   = item.dataset.accountId;
        currentAccountName = item.dataset.accountName;
        currentClientId    = null;
        currentClientName  = '';
        allContacts        = [];
        newMsgCount        = 0;

        // Update the chat section header to show which account is selected
        updateChatSectionHeader(item);

        // Reset chat view
        document.getElementById('chatWelcome').style.display = 'flex';
        document.getElementById('chatView').style.display = 'none';

        // Enable chats nav item and switch to chats panel
        const chatsNav = document.getElementById('dashNavChats');
        chatsNav.style.opacity = '1';
        chatsNav.style.cursor = 'pointer';
        chatsNav.disabled = false;
        showDashPanel('chats');

        // Load contacts for this account
        loadContacts(currentAccountId);
    };

    function updateChatSectionHeader(item) {
        const title = document.getElementById('chatSectionTitle');
        const subtitle = document.getElementById('chatSectionSubtitle');
        if (!title || !subtitle) return;
        if (!item) {
            title.textContent = 'Conversations';
            subtitle.textContent = 'Select an account first';
            return;
        }
        title.textContent = item.dataset.accountName;
        subtitle.textContent = item.dataset.accountPhone + (IS_TG ? ' • Telegram' : ' • WhatsApp');
    }

    // ═══════════════════════ DASHBOARD NAV PANEL SWITCHING ═══════════════════════
    let activeDashPanel = 'accounts'; // 'accounts' | 'chats'

    window.showDashPanel = function(panel) {
        if (panel === 'chats' && document.getElementById('dashNavChats').disabled) return;
        activeDashPanel = panel;
        document.getElementById('accountsView').style.display = panel === 'accounts' ? 'flex' : 'none';
        document.getElementById('chatsView').style.display = panel === 'chats' ? 'flex' : 'none';
        // Update nav active state
        document.getElementById('dashNavAccounts').classList.toggle('active', panel === 'accounts');
        document.getElementById('dashNavChats').classList.toggle('active', panel === 'chats');
    };

    // ═══════════════════════ FILTER TABS ═══════════════════════
    // Declared BEFORE selectAccount() is called so loadContacts() can read it.
    let activeContactFilter = 'all';

    // Auto-select first account on load
    const firstAcct = document.querySelector('.account-list-item');
    if (firstAcct) {
        selectAccount(firstAcct);
    }

    // Restore account nav panel and item expand/collapse state from localStorage
    try { restoreAccountNavState(); } catch (e) {}

    function setContactFilter(filter, btn) {
        activeContactFilter = filter;
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        if (btn) btn.classList.add('active');

        // If contacts are already loaded, filter them immediately.
        // Otherwise reload from server so the filter is applied server-side too.
        if (allContacts.length > 0) {
            applyContactFilter();
        } else if (currentAccountId) {
            loadContacts(currentAccountId);
        }
    }

    // ═══════════════════════ ACCOUNT NAV ═══════════════════════
    function toggleAccountNav() {
        const panel = document.getElementById('accountNavPanel');
        const chevron = document.getElementById('accountNavChevron');
        const hidden = panel.style.display === 'none';
        panel.style.display = hidden ? 'block' : 'none';
        chevron.style.transform = hidden ? 'rotate(180deg)' : 'rotate(0deg)';
        try { localStorage.setItem('staff_account_nav_open', hidden ? '1' : '0'); } catch (e) {}
    }

    function toggleAccountNavItem(mainEl) {
        const item = mainEl.closest('.account-nav-item');
        const access = item.querySelector('.account-nav-access');
        if (!access) return;
        const open = item.classList.contains('open');
        item.classList.toggle('open', !open);
        access.style.display = open ? 'none' : 'block';
        try { localStorage.setItem('staff_account_nav_' + item.getAttribute('data-account-id'), open ? '0' : '1'); } catch (e) {}
    }

    window.selectAccountFromNav = function(mainEl) {
        const item = mainEl.closest('.account-nav-item');
        if (!item) return;
        const accountId = item.getAttribute('data-account-id');

        // Toggle expand/collapse
        const access = item.querySelector('.account-nav-access');
        if (access) {
            const open = item.classList.contains('open');
            item.classList.toggle('open', !open);
            access.style.display = open ? 'none' : 'block';
        }

        // Sync the account-list-item UI and load chats
        const acctItem = document.querySelector('.account-list-item[data-account-id="' + accountId + '"]');
        if (acctItem) {
            selectAccount(acctItem);
        } else {
            currentAccountId = accountId;
            loadContacts(accountId);
        }
    };

    function restoreAccountNavState() {
        try {
            const globalOpen = localStorage.getItem('staff_account_nav_open');
            if (globalOpen === '0') {
                const panel = document.getElementById('accountNavPanel');
                const chevron = document.getElementById('accountNavChevron');
                if (panel) panel.style.display = 'none';
                if (chevron) chevron.style.transform = 'rotate(0deg)';
            }
            document.querySelectorAll('.account-nav-item').forEach(item => {
                const id = item.getAttribute('data-account-id');
                const saved = localStorage.getItem('staff_account_nav_' + id);
                const access = item.querySelector('.account-nav-access');
                if (saved === '1' && access) {
                    item.classList.add('open');
                    access.style.display = 'block';
                }
            });
        } catch (e) {}
    }

    function updateNavUnread(accountId, unreadCount) {
        const badge = document.getElementById('nav-unread-' + accountId);
        if (!badge) return;
        if (unreadCount > 0) {
            badge.textContent = unreadCount;
            badge.style.display = 'inline-flex';
        } else {
            badge.style.display = 'none';
        }
    }

    // ═══════════════════════ LOAD CONTACTS ═══════════════════════
    function loadContacts(accountId) {
        document.getElementById('contactList').innerHTML = buildSkeletons(6);

        const filterParam = activeContactFilter === 'unread' ? '&filter=unread' : '';
        fetch('/staff/accounts/' + accountId + '/chats?per_page=100&page=1' + filterParam)
            .then(r => {
                if (r.status === 403) {
                    return Promise.reject('not_assigned');
                }
                if (!r.ok) {
                    return Promise.reject('server_error');
                }
                return r.json();
            })
            .then(response => {
                const contacts = response.data || [];
                allContacts = contacts;
                renderContactList(contacts, accountId);
            })
            .catch(err => {
                const msg = err === 'not_assigned'
                    ? '<div class="contact-empty"><div class="icon">🔒</div><p>You don\'t have access to this account</p></div>'
                    : '<div class="contact-empty"><div class="icon">⚠</div><p>Failed to load conversations</p><p style="font-size:11px;color:var(--text-secondary);margin-top:4px;">Please refresh or contact admin</p></div>';
                document.getElementById('contactList').innerHTML = msg;
            });
    }

    function buildSkeletons(n) {
        let html = '';
        for (let i = 0; i < n; i++) {
            html += `<div style="padding:12px 16px;display:flex;gap:12px;align-items:center;border-bottom:1px solid var(--border-subtle);">
                <div style="width:48px;height:48px;border-radius:50%;background:var(--bubble-in);flex-shrink:0;"></div>
                <div style="flex:1;">
                    <div style="height:14px;width:${60+Math.random()*80}px;background:var(--bubble-in);border-radius:4px;margin-bottom:8px;"></div>
                    <div style="height:11px;width:${80+Math.random()*100}px;background:rgba(255,255,255,0.04);border-radius:4px;"></div>
                </div>
            </div>`;
        }
        return html;
    }

    function formatIdentifier(ident) {
        if (!ident) return '';
        const s = String(ident).trim();
        if (IS_TG) {
            if (s.startsWith('+') || /^\d+$/.test(s)) {
                return s.startsWith('+') ? s : ('+' + s);
            }
            return s.startsWith('@') ? s : ('@' + s);
        }
        return formatPhone(s);
    }

    function getClientDisplayName(c) {
        if (!c) return '';
        const name = (c.display_name || '').trim();
        const genericNames = ['client', 'contact', 'crm contact', 'user', 'unknown', 'none', 'null'];
        if (name && !genericNames.includes(name.toLowerCase())) {
            return name;
        }
        return formatIdentifier(c.identifier);
    }

    function renderContactList(contacts, accountId) {
        const list = document.getElementById('contactList');
        const countEl = document.getElementById('chatCount');
        if (countEl) {
            countEl.style.display = '';
            countEl.textContent = contacts.length + (contacts.length === 1 ? ' chat' : ' chats');
        }

        if (!contacts || contacts.length === 0) {
            list.innerHTML = `<div class="contact-empty">
                <div class="icon">${IS_TG ? '✈' : '💬'}</div>
                <p>No conversations yet</p>
            </div>`;
            return;
        }

        let html = '';
        contacts.forEach(c => {
            const name     = getClientDisplayName(c);
            const initial  = (name || c.identifier || '?')[0].toUpperCase();
            // A media message shows "📷 Photo" style text rather than a blank line.
            const lastMsg  = previewText(c.last_message, c.last_message_type, c.last_media_name);
            const lastTime = c.last_message_at ? formatTime(c.last_message_at) : '';
            const unread   = c.unread_count || 0;
            const isSentByMe = c.last_sender_type === 'staff' || c.last_sender_type === 'system';
            const tickHtml = isSentByMe ? `<span class="tick ${unread===0?'read':'sent'}">✓✓</span>` : '';
            const isActive = String(c.id) === String(currentClientId) ? ' active' : '';

            html += `<div
                class="contact-item${isActive}"
                data-client-id="${c.id}"
                data-client-name="${escHtml(name)}"
                onclick="openChat('${accountId}',${c.id},'${escHtml(name)}')"
            >
                <div class="contact-avatar">${initial}</div>
                <div class="contact-info">
                    <div class="contact-row1">
                        <span class="contact-name">${escHtml(name)}</span>
                        <span class="contact-time ${unread>0?'unread-time':''}">${lastTime}</span>
                    </div>
                    <div class="contact-row2">
                        <span class="contact-preview">
                            ${tickHtml}
                            ${escHtml(lastMsg.substring(0,50))}
                        </span>
                        ${unread > 0 ? `<span class="unread-badge" id="contact-badge-${c.id}">${unread}</span>` : `<span class="unread-badge" id="contact-badge-${c.id}" style="display:none;">0</span>`}
                    </div>
                </div>
            </div>`;
        });

        list.innerHTML = html;
    }

    /** Sidebar preview label: caption if present, else a media descriptor. */
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

    // ═══════════════════════ CONTACT DETAILS PANEL ═══════════════════════
    window.showContactDetails = function() {
        if (!currentClientId) return;
        const contact = allContacts.find(c => c.id == currentClientId);
        if (!contact) return;

        const name = getClientDisplayName(contact);
        const initial = (name || '?')[0].toUpperCase();

        document.getElementById('detail-avatar').textContent = initial;
        document.getElementById('detail-name').textContent = name;
        document.getElementById('detail-sub').textContent = IS_TG ? 'Telegram User' : 'WhatsApp Contact';
        document.getElementById('detail-identifier').innerHTML =
            '<span style="color:var(--text-secondary);">' + (IS_TG ? 'Identifier' : 'Phone') + '</span><br>' +
            formatIdentifier(contact.identifier);
        document.getElementById('detail-platform').innerHTML =
            '<span style="color:var(--text-secondary);">Platform</span><br>' +
            (IS_TG ? '✈ Telegram' : '💬 WhatsApp');
        document.getElementById('detail-status').innerHTML =
            '<span style="color:var(--text-secondary);">Status</span><br>' + (contact.status || 'active');

        document.getElementById('contactDetailsPanel').style.display = 'block';
    };

    window.hideContactDetails = function() {
        document.getElementById('contactDetailsPanel').style.display = 'none';
    };

    // Search/filter contacts
    window.filterContacts = function(query) {
        applyContactFilter();
    };

    function getFilteredContacts() {
        const query = document.getElementById('contactSearch')?.value || '';
        let list = allContacts;

        if (activeContactFilter === 'unread') {
            list = list.filter(c => c.unread_count > 0);
        }

        if (query.trim()) {
            const q = query.toLowerCase();
            list = list.filter(c =>
                c.identifier.toLowerCase().includes(q) ||
                (c.last_message || '').toLowerCase().includes(q)
            );
        }

        return list;
    }

    window.applyContactFilter = function() {
        renderContactList(getFilteredContacts(), currentAccountId);
    };

    // ═══════════════════════ OPEN CHAT ═══════════════════════
    window.openChat = function(accountId, clientId, clientName) {
        currentAccountId  = accountId;
        currentClientId   = clientId;
        currentClientName = clientName;
        newMsgCount = 0;

        // Stop previous poll
        if (window._chatPoll) clearInterval(window._chatPoll);

        // Highlight active contact
        document.querySelectorAll('.contact-item').forEach(el => el.classList.remove('active'));
        const el = document.querySelector(`.contact-item[data-client-id="${clientId}"]`);
        if (el) el.classList.add('active');

        // Show chat view
        document.getElementById('chatWelcome').style.display = 'none';
        const cv = document.getElementById('chatView');
        cv.style.display = 'flex';

        // Update header - show display_name for TG, formatted phone for WA
        const contact = allContacts.find(c => c.id == clientId);
        const displayName = contact ? getClientDisplayName(contact) : formatIdentifier(clientName);
        const initial = (displayName || '?')[0].toUpperCase();
        document.getElementById('headerAvatar').textContent = initial;
        document.getElementById('headerName').textContent = displayName;
        document.getElementById('headerStatus').textContent = 'Loading…';
        document.getElementById('headerStatus').className = 'chat-header-status';

        // Clear messages
        const area = document.getElementById('messagesArea');
        area.innerHTML = buildSkeletons(4);

        // Load messages
        fetch(`/staff/accounts/${accountId}/chats/${clientId}/messages`)
            .then(r => r.json())
            .then(data => {
                renderMessages(data.messages || data);
                const count = (data.messages || data).length;
                document.getElementById('headerStatus').textContent =
                    `${count} message${count !== 1 ? 's' : ''}`;

                // Wait for DOM to stabilize (images load) then scroll to bottom
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        scrollToBottom(true);
                    });
                });

                // Clear unread badge for this contact
                clearContactBadge(clientId);
            })
            .catch(() => {
                area.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-secondary);">Failed to load messages</div>';
            });

        // On mobile, show chat panel
        document.getElementById('crmShell').classList.add('chat-open');

        // Fallback periodic sync: catch any missed messages without wiping DOM
        window._chatPoll = setInterval(() => {
            if (!currentAccountId || !currentClientId) return;
            fetch(`/staff/accounts/${currentAccountId}/chats/${currentClientId}/messages`)
                .then(r => r.json())
                .then(data => {
                    const msgs = data.messages || data;
                    if (!msgs || !msgs.length) return;
                    let appendedAny = false;
                    msgs.forEach(m => {
                        if (!document.getElementById('msg-' + m.id)) {
                            appendMessage(m, false);
                            appendedAny = true;
                        }
                    });
                    if (appendedAny) {
                        const count = msgs.length;
                        const headerStatus = document.getElementById('headerStatus');
                        if (headerStatus) {
                            headerStatus.textContent = `${count} message${count !== 1 ? 's' : ''}`;
                        }
                    }
                })
                .catch(() => {});
        }, 12000);
    };

    function renderMessages(messages) {
        const area = document.getElementById('messagesArea');
        if (!messages.length) {
            area.innerHTML = '<div style="text-align:center;padding:60px 20px;color:var(--text-secondary);font-size:13px;">No messages yet. Say hello! 👋</div>';
            return;
        }

        lastDateLabel = null;
        let html = '';
        messages.forEach((msg, i) => {
            const prev = i > 0 ? messages[i-1] : null;
            html += buildMessageHTML(msg, prev);
        });
        area.innerHTML = html;

        // After images load, re-scroll to bottom so latest is visible
        area.querySelectorAll('img[loading="lazy"], img:not([loading])').forEach(img => {
            img.addEventListener('load', () => scrollToBottom(true), { once: true });
        });
    }

    function buildMessageHTML(msg, prevMsg) {
        let html = '';

        // Date separator
        const msgDate = new Date(msg.created_at);
        const dateLabel = formatDateLabel(msgDate);
        if (dateLabel !== lastDateLabel) {
            html += `<div class="date-separator"><span>${dateLabel}</span></div>`;
            lastDateLabel = dateLabel;
        }

        const isOut    = msg.sender_type === 'staff' || msg.sender_type === 'system';
        const isSystem = msg.sender_type === 'system';
        const rowClass = isSystem ? 'system' : (isOut ? 'out' : 'in');
        const timeStr  = formatTimeShort(msgDate);
        const tickHTML = isOut && !isSystem
            ? `<span class="bubble-ticks ${msg.is_read ? 'read' : 'sent'}">✓✓</span>`
            : '';

        if (isSystem) {
            html += `<div class="msg-row system" id="msg-${msg.id}" data-id="${msg.id}" data-ts="${msg.created_at}">
                <div class="bubble"><span style="font-style:italic;">${escHtml(msg.message_body)}</span></div>
            </div>`;
        } else {
            const mediaHTML = buildMediaHTML(msg);
            const body = msg.message_body || '';
            // A media-only message needs no empty text line.
            const textHTML = body
                ? `<div class="bubble-text">${escHtml(body)}<span style="display:inline-block;width:52px;"></span></div>`
                : '';

            html += `<div class="msg-row ${rowClass}" id="msg-${msg.id}" data-id="${msg.id}" data-ts="${msg.created_at}">
                <div class="bubble${msg._uploading ? ' uploading' : ''}">
                    ${mediaHTML}
                    ${textHTML}
                    <div class="bubble-meta">
                        <span class="bubble-time">${timeStr}</span>
                        ${tickHTML}
                    </div>
                </div>
            </div>`;
        }

        return html;
    }

    /** Render the attachment portion of a bubble, if the message carries one. */
    function buildMediaHTML(msg) {
        const type = msg.message_type || 'text';
        if (type === 'text') return '';

        // Use explicit media_url if provided (broadcast), or build URL from
        // media_path (loaded from DB), or use local blob URL (optimistic send).
        const url = msg.media_url
            || (msg.media_path ? '/media/' + msg.media_path : null)
            || msg._localUrl;
        if (!url) return '';

        const safeUrl = escAttr(url);
        const name = escHtml(msg.media_name || 'file');
        const nameAttr = escAttr(msg.media_name || 'file');

        switch (type) {
            case 'image':
                return `<div class="bubble-media">
                    <img src="${safeUrl}" alt="${nameAttr}" loading="lazy"
                         onclick="openLightbox('${safeUrl}','image','${nameAttr}')">
                </div>`;

            case 'sticker':
                return `<div class="bubble-media sticker">
                    <img src="${safeUrl}" alt="sticker" loading="lazy">
                </div>`;

            case 'video':
                return `<div class="bubble-media">
                    <video src="${safeUrl}" controls preload="metadata"></video>
                </div>`;

            case 'audio':
                return `<div class="bubble-media">
                    <audio src="${safeUrl}" controls preload="metadata"></audio>
                </div>`;

            default:
                return `<a class="doc-chip" href="${safeUrl}" download="${nameAttr}" target="_blank" rel="noopener">
                    <span class="doc-icon">${docIcon(msg.media_name)}</span>
                    <span class="doc-meta">
                        <span class="doc-name">${name}</span>
                        <span class="doc-size">${formatBytes(msg.media_size)}</span>
                    </span>
                </a>`;
        }
    }

    function docIcon(name) {
        const ext = (name || '').split('.').pop().toLowerCase();
        if (['pdf'].includes(ext)) return '📕';
        if (['doc','docx','odt','rtf'].includes(ext)) return '📘';
        if (['xls','xlsx','csv','ods'].includes(ext)) return '📗';
        if (['ppt','pptx','odp'].includes(ext)) return '📙';
        if (['zip','rar','7z','tar','gz'].includes(ext)) return '🗜️';
        return '📄';
    }

    function formatBytes(bytes) {
        if (!bytes) return '';
        const units = ['B','KB','MB','GB'];
        let i = 0, n = bytes;
        while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
        return `${n.toFixed(n < 10 && i > 0 ? 1 : 0)} ${units[i]}`;
    }

    function appendMessage(msg, animate) {
        const area = document.getElementById('messagesArea');

        // Remove empty placeholder if present
        const placeholder = area.querySelector('[data-placeholder]');
        if (placeholder) placeholder.remove();

        const prevMsgs = area.querySelectorAll('.msg-row');
        const prev = prevMsgs.length ? { created_at: prevMsgs[prevMsgs.length-1].dataset.ts } : null;

        const wrapper = document.createElement('div');
        wrapper.innerHTML = buildMessageHTML(msg, prev);
        const nodes = wrapper.childNodes;

        nodes.forEach(node => {
            if (animate && node.classList) node.classList.add('new');
            area.appendChild(node.cloneNode(true));
        });

        if (isAtBottom) {
            scrollToBottom();
        } else {
            newMsgCount++;
            const btn = document.getElementById('scrollBtn');
            btn.style.display = 'flex';
            let pill = btn.querySelector('.unread-pill');
            if (!pill) {
                pill = document.createElement('div');
                pill.className = 'unread-pill';
                btn.appendChild(pill);
            }
            pill.textContent = newMsgCount;
        }
    }

    // ═══════════════════════ ATTACHMENTS ═══════════════════════
    const MAX_UPLOAD_BYTES = 64 * 1024 * 1024;
    let pendingFile = null;
    let pendingObjectUrl = null;

    window.toggleAttachMenu = function(e) {
        if (e) e.stopPropagation();
        document.getElementById('attachMenu').classList.toggle('open');
    };

    // Any outside click dismisses the menu.
    document.addEventListener('click', function(e) {
        const menu = document.getElementById('attachMenu');
        if (menu && !e.target.closest('.attach-wrap')) menu.classList.remove('open');
    });

    window.pickFile = function(accept) {
        const input = document.getElementById('fileInput');
        input.accept = accept;
        input.value = '';           // re-picking the same file must still fire change
        input.click();
        document.getElementById('attachMenu').classList.remove('open');
    };

    window.onFilePicked = function(e) {
        const file = e.target.files && e.target.files[0];
        if (file) stageAttachment(file);
    };

    function stageAttachment(file) {
        if (file.size > MAX_UPLOAD_BYTES) {
            alert(`File is too large (${formatBytes(file.size)}). Maximum is 64 MB.`);
            return;
        }

        clearAttachment();
        pendingFile = file;

        const preview = document.getElementById('attachPreview');
        const thumb = document.getElementById('apThumb');
        document.getElementById('apName').textContent = file.name;
        document.getElementById('apSize').textContent = formatBytes(file.size);

        if (file.type.startsWith('image/') || file.type.startsWith('video/')) {
            pendingObjectUrl = URL.createObjectURL(file);
            thumb.innerHTML = file.type.startsWith('image/')
                ? `<img src="${pendingObjectUrl}" alt="">`
                : `<video src="${pendingObjectUrl}" muted></video>`;
        } else {
            const icon = file.type.startsWith('audio/') ? '🎵' : docIcon(file.name);
            thumb.innerHTML = `<div class="ap-icon">${icon}</div>`;
        }

        preview.classList.add('open');
        document.getElementById('msgComposer').focus();
    }

    window.clearAttachment = function() {
        pendingFile = null;
        if (pendingObjectUrl) {
            URL.revokeObjectURL(pendingObjectUrl);
            pendingObjectUrl = null;
        }
        document.getElementById('attachPreview').classList.remove('open');
        document.getElementById('apThumb').innerHTML = '';
        document.getElementById('fileInput').value = '';
    };

    // Paste an image straight from the clipboard.
    document.addEventListener('paste', function(e) {
        if (!currentClientId) return;
        const items = (e.clipboardData || {}).items || [];
        for (const item of items) {
            if (item.kind === 'file') {
                const file = item.getAsFile();
                if (file) {
                    e.preventDefault();
                    stageAttachment(file);
                    return;
                }
            }
        }
    });

    // Drag-and-drop onto the message area.
    (function initDropZone() {
        const area = document.getElementById('messagesArea');
        const overlay = document.getElementById('dropOverlay');
        if (!area || !overlay) return;

        let depth = 0;
        const hasFiles = (e) => Array.from(e.dataTransfer?.types || []).includes('Files');

        area.addEventListener('dragenter', (e) => {
            if (!hasFiles(e) || !currentClientId) return;
            e.preventDefault();
            if (++depth === 1) overlay.classList.add('open');
        });
        area.addEventListener('dragover', (e) => { if (hasFiles(e)) e.preventDefault(); });
        area.addEventListener('dragleave', () => {
            if (--depth <= 0) { depth = 0; overlay.classList.remove('open'); }
        });
        area.addEventListener('drop', (e) => {
            if (!hasFiles(e) || !currentClientId) return;
            e.preventDefault();
            depth = 0;
            overlay.classList.remove('open');
            const file = e.dataTransfer.files[0];
            if (file) stageAttachment(file);
        });
    })();

    // ═══════════════════════ LIGHTBOX ═══════════════════════
    window.openLightbox = function(url, type, name) {
        const box = document.getElementById('lightbox');
        const content = document.getElementById('lbContent');
        const dl = document.getElementById('lbDownload');

        content.innerHTML = type === 'video'
            ? `<video src="${escAttr(url)}" controls autoplay></video>`
            : `<img src="${escAttr(url)}" alt="${escAttr(name || '')}">`;

        dl.href = url;
        dl.setAttribute('download', name || 'download');
        box.classList.add('open');
    };

    window.closeLightbox = function(e, force) {
        // Clicking the media itself shouldn't dismiss the viewer.
        if (!force && e && e.target.closest('#lbContent')) return;
        const box = document.getElementById('lightbox');
        box.classList.remove('open');
        document.getElementById('lbContent').innerHTML = '';
    };

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeLightbox(null, true);
    });

    // ═══════════════════════ SEND MESSAGE ═══════════════════════
    window.sendMessage = function() {
        const composer = document.getElementById('msgComposer');
        const text = composer.innerText.trim();
        const file = pendingFile;

        if ((!text && !file) || !currentAccountId || !currentClientId) return;

        const tempId = 'tmp-' + Date.now();
        const tempMsg = {
            id: tempId,
            sender_type: 'staff',
            message_type: file ? guessType(file.type) : 'text',
            message_body: text,
            media_name: file ? file.name : null,
            media_size: file ? file.size : null,
            _localUrl: file ? (pendingObjectUrl || URL.createObjectURL(file)) : null,
            _uploading: !!file,
            is_read: false,
            created_at: new Date().toISOString(),
        };
        appendMessage(tempMsg, false);

        const body = new FormData();
        if (text) body.append('message', text);
        if (file) body.append('attachment', file);

        // Reset the composer immediately; the object URL stays alive for the
        // optimistic bubble, so detach it from the preview without revoking.
        composer.innerText = '';
        composerInput();
        pendingFile = null;
        pendingObjectUrl = null;
        document.getElementById('attachPreview').classList.remove('open');
        document.getElementById('apThumb').innerHTML = '';
        document.getElementById('fileInput').value = '';

        fetch(`/staff/accounts/${currentAccountId}/chats/${currentClientId}/send`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
            },
            // No Content-Type header: the browser sets the multipart boundary.
            body: body,
        })
        .then(r => r.json().then(d => ({ ok: r.ok, data: d })))
        .then(({ ok, data }) => {
            const tmp = document.getElementById('msg-' + tempId);

            if (data.message) {
                if (tmp) {
                    const real = document.createElement('div');
                    real.innerHTML = buildMessageHTML(data.message, null);
                    tmp.replaceWith(real.firstElementChild);
                }
                if (currentAccountId) loadContacts(currentAccountId);
            }

            if (!ok || !data.success) {
                console.warn('[send] delivery issue:', data.error || 'unknown');
                const row = document.getElementById('msg-' + (data.message?.id ?? tempId));
                const bubble = row?.querySelector('.bubble');
                if (bubble) {
                    bubble.classList.remove('uploading');
                    bubble.style.border = '1px solid var(--danger, #ef4444)';
                    bubble.title = data.error || 'Not delivered';
                }
            }
        })
        .catch((err) => {
            console.error('[send] failed:', err);
            const bubble = document.getElementById('msg-' + tempId)?.querySelector('.bubble');
            if (bubble) {
                bubble.classList.remove('uploading');
                bubble.style.border = '1px solid var(--danger, #ef4444)';
                bubble.title = 'Send failed';
            }
        });
    };

    function guessType(mime) {
        mime = (mime || '').toLowerCase();
        if (mime.startsWith('image/webp')) return 'sticker';
        if (mime.startsWith('image/')) return 'image';
        if (mime.startsWith('video/')) return 'video';
        if (mime.startsWith('audio/')) return 'audio';
        return 'document';
    }

    // Enter to send (shift+enter = newline)
    window.composerKeydown = function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    };

    window.composerInput = function() {
        const c = document.getElementById('msgComposer');
        // Limit height
        c.style.height = 'auto';
    };

    // ═══════════════════════ SOUND & NOTIFICATIONS ═══════════════════════
    let isSoundEnabled = localStorage.getItem('crm_audio_enabled') !== '0';
    let audioUnlocked = false;
    let originalDocTitle = document.title;
    let tabFlashTimer = null;

    function updateSoundButtonUI() {
        const btn = document.getElementById('soundToggleBtn');
        if (!btn) return;
        if (isSoundEnabled) {
            btn.innerHTML = '🔔';
            btn.title = 'Notification Sound: ON (Click to toggle/test)';
            btn.classList.remove('sound-muted');
        } else {
            btn.innerHTML = '🔕';
            btn.title = 'Notification Sound: MUTED (Click to enable)';
            btn.classList.add('sound-muted');
        }
    }
    updateSoundButtonUI();

    window.toggleSound = function() {
        isSoundEnabled = !isSoundEnabled;
        localStorage.setItem('crm_audio_enabled', isSoundEnabled ? '1' : '0');
        updateSoundButtonUI();
        if (isSoundEnabled) {
            unlockAudio();
            playNotificationSound(true);
        }
    };

    function unlockAudio() {
        if (audioUnlocked) return;
        audioUnlocked = true;
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (AudioCtx) {
                window._audioCtx = window._audioCtx || new AudioCtx();
                if (window._audioCtx.state === 'suspended') {
                    window._audioCtx.resume();
                }
            }
            const audio = document.getElementById('notify-sound');
            if (audio) {
                audio.muted = true;
                const p = audio.play();
                if (p && typeof p.then === 'function') {
                    p.then(() => {
                        audio.pause();
                        audio.muted = false;
                        audio.currentTime = 0;
                    }).catch(() => {
                        if (audio) audio.muted = false;
                    });
                }
            }
        } catch (e) {}
    }

    ['click', 'keydown', 'touchstart', 'pointerdown'].forEach(ev => {
        window.addEventListener(ev, unlockAudio, { once: true, passive: true });
    });

    function playWebAudioChime() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = window._audioCtx || (window._audioCtx = new AudioCtx());
            if (ctx.state === 'suspended') {
                ctx.resume();
            }
            const now = ctx.currentTime;

            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(880, now);
            gain1.gain.setValueAtTime(0.2, now);
            gain1.gain.exponentialRampToValueAtTime(0.0001, now + 0.25);
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start(now);
            osc1.stop(now + 0.25);

            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(1174.66, now + 0.08);
            gain2.gain.setValueAtTime(0.22, now + 0.08);
            gain2.gain.exponentialRampToValueAtTime(0.0001, now + 0.45);
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.start(now + 0.08);
            osc2.stop(now + 0.45);
        } catch (e) {
            console.warn('[Audio] WebAudio chime failed:', e);
        }
    }

    function playNotificationSound(force) {
        if (!force && !isSoundEnabled) return;

        const audio = document.getElementById('notify-sound');
        if (audio) {
            try {
                audio.currentTime = 0;
                const p = audio.play();
                if (p && typeof p.then === 'function') {
                    p.catch(() => {
                        playWebAudioChime();
                    });
                }
            } catch (e) {
                playWebAudioChime();
            }
        } else {
            playWebAudioChime();
        }
    }

    function flashTabTitle(count) {
        if (!document.hidden) return;
        if (tabFlashTimer) clearInterval(tabFlashTimer);
        let toggle = false;
        tabFlashTimer = setInterval(() => {
            if (!document.hidden) {
                clearInterval(tabFlashTimer);
                tabFlashTimer = null;
                document.title = originalDocTitle;
                return;
            }
            document.title = toggle ? `🔔 (${count || 1} New Msg) ${originalDocTitle}` : originalDocTitle;
            toggle = !toggle;
        }, 1200);
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            if (tabFlashTimer) {
                clearInterval(tabFlashTimer);
                tabFlashTimer = null;
            }
            document.title = originalDocTitle;
        }
    });

    // ═══════════════════════ REALTIME — WEBSOCKET ═══════════════════════
    (function initRealtime() {
        let attempts = 0;
        const maxAttempts = 50;

        function tryInit() {
            if (typeof Echo === 'undefined') {
                if (++attempts <= maxAttempts) {
                    setTimeout(tryInit, 100);
                    return;
                }
                console.error('[Realtime] Echo not loaded after 5s');
                setRtState('error', 'offline');
                return;
            }

            console.log('[Realtime] ✅ Echo ready (attempt ' + attempts + ')');
            const conn = Echo.connector.pusher.connection;

            conn.bind('connected', () => {
                console.log('[Realtime] ✅ Connected, socket=' + conn.socket_id);
                setRtState('connected', 'connected');
            });
            conn.bind('error', (e) => {
                console.error('[Realtime] connection error', e);
                setRtState('error', 'conn error');
            });
            conn.bind('disconnected', () => {
                console.warn('[Realtime] disconnected');
                setRtState('error', 'disconnected');
            });
            conn.bind('connecting', () => {
                setRtState('connecting', 'connecting…');
            });

            if (conn.state === 'connected') {
                setRtState('connected', 'connected');
            }

            // Private channel for this staff member
            const ch = Echo.private('staff.' + STAFF_ID);

            ch.listen('.NewMessageReceived', function(e) {
                console.log('[Realtime] 📩 NewMessageReceived', e);
                const msg = e.message;
                if (!msg) return;

                // Flash status indicator
                const txt = document.getElementById('rt-text');
                if (txt) txt.textContent = 'msg ↓ ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });

                const isCurrentChat =
                    String(msg.account_id) === String(currentAccountId) &&
                    String(msg.client_id)  === String(currentClientId);

                const isOwnSentMsg = msg.sender_type === 'staff' && msg.staff_id === STAFF_ID;
                const isClientMsg = msg.sender_type === 'client';

                if (isCurrentChat) {
                    if (isOwnSentMsg) {
                        const existingReal = document.getElementById('msg-' + msg.id);
                        if (!existingReal) {
                            const tempBubble = document.querySelector('.msg-row.out[id^="msg-tmp-"]');
                            if (tempBubble) {
                                const realDiv = document.createElement('div');
                                realDiv.innerHTML = buildMessageHTML(msg, null);
                                tempBubble.replaceWith(realDiv.firstElementChild);
                            } else {
                                appendMessage(msg, true);
                            }
                        }
                    } else {
                        const existing = document.getElementById('msg-' + msg.id);
                        if (!existing) {
                            appendMessage(msg, true);
                        }
                        // Mark as read on server
                        fetch(`/staff/accounts/${msg.account_id}/chats/${msg.client_id}/messages`);
                        if (isClientMsg) {
                            playNotificationSound();
                            flashTabTitle(1);
                            if (document.hidden) {
                                showRealtimeToast(msg);
                            }
                        }
                    }
                } else {
                    if (isClientMsg) {
                        playNotificationSound();
                        flashTabTitle(1);
                        updateTabBadge(msg.account_id);
                        showRealtimeToast(msg);
                    }
                }

                // Sync sidebar contacts
                if (String(msg.account_id) === String(currentAccountId)) {
                    syncContactOnMessage(msg, isCurrentChat);
                }
            });

            ch.subscribed(() => {
                console.log('[Realtime] ✅ Subscribed to staff.' + STAFF_ID);
                setRtState('connected', 'subscribed ✓');
            });

            ch.error((err) => {
                console.error('[Realtime] ❌ Channel error', err);
                setRtState('error', 'auth failed');
            });
        }

        tryInit();
    })();

    function updateTabBadge(accountId) {
        const badge = document.getElementById('tab-unread-' + accountId);
        if (!badge) return;
        badge.style.display = 'flex';
        const cur = parseInt(badge.textContent || '0');
        badge.textContent = cur + 1;
    }

    function clearContactBadge(clientId) {
        const contact = allContacts.find(c => String(c.id) === String(clientId));
        if (contact) contact.unread_count = 0;

        const badge = document.getElementById('contact-badge-' + clientId);
        if (badge) { badge.style.display = 'none'; badge.textContent = '0'; }

        const item = document.querySelector(`.contact-item[data-client-id="${clientId}"]`);
        if (item) {
            const timeEl = item.querySelector('.contact-time');
            if (timeEl) timeEl.classList.remove('unread-time');
        }
    }

    // ═══════════════════════ REALTIME TOAST POPUP NOTIFICATIONS ═══════════════════════
    function showRealtimeToast(msg) {
        const container = document.getElementById('rt-toast-container');
        if (!container || !msg) return;

        const accountId = msg.account_id;
        const clientId  = msg.client_id;
        const clientObj = msg.client || {};
        const acctObj   = msg.account || {};

        const clientDisplayName = getClientDisplayName(clientObj) || (clientObj.first_name ? clientObj.first_name : '') || formatIdentifier(clientObj.identifier) || ('Client #' + clientId);
        const initial = (clientDisplayName || '?')[0].toUpperCase();

        const platform = (acctObj.platform || (IS_TG ? 'telegram' : 'whatsapp')).toLowerCase();
        const isTgMsg = platform.includes('tg') || platform.includes('telegram');
        const acctName = acctObj.account_name || acctObj.phone_number || ('Account #' + accountId);
        const platformLabel = isTgMsg ? 'Telegram' : 'WhatsApp';
        const platformIcon = isTgMsg ? '✈' : '💬';
        const themeClass = isTgMsg ? 'tg-theme' : 'wa-theme';

        const preview = previewText(msg.message_body, msg.message_type, msg.media_name) || 'New message';

        const toast = document.createElement('div');
        toast.className = `rt-toast ${themeClass}`;
        toast.dataset.accountId = accountId;
        toast.dataset.clientId = clientId;

        toast.innerHTML = `
            <div class="rt-toast-top">
                <div class="rt-toast-user">
                    <div class="rt-toast-avatar" style="background: ${isTgMsg ? 'linear-gradient(135deg, #229ED9, #1a7ab5)' : 'linear-gradient(135deg, #25D366, #128C7E)'};">
                        ${escHtml(initial)}
                        <span class="rt-toast-platform-icon">${platformIcon}</span>
                    </div>
                    <div class="rt-toast-info">
                        <div class="rt-toast-name">${escHtml(clientDisplayName)}</div>
                        <div class="rt-toast-account">${escHtml(acctName)} • ${platformLabel}</div>
                    </div>
                </div>
                <div class="rt-toast-controls">
                    <span class="rt-toast-time">Just now</span>
                    <button class="rt-toast-close" title="Dismiss" onclick="dismissToast(event, this)">✕</button>
                </div>
            </div>
            <div class="rt-toast-body">${escHtml(preview)}</div>
            <div class="rt-toast-footer">
                <span>Click to reply</span>
                <svg class="rt-toast-arrow" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M12.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="rt-toast-progress-bar" style="background: ${isTgMsg ? '#229ED9' : '#25D366'};"></div>
        `;

        toast.addEventListener('click', (e) => {
            if (e.target.closest('.rt-toast-close')) return;
            dismissToastEl(toast);
            navigateToChat(accountId, clientId, clientDisplayName);
        });

        let remainingMs = 7000;
        let startTime = Date.now();
        let timerId = setTimeout(() => {
            dismissToastEl(toast);
        }, remainingMs);

        toast.addEventListener('mouseenter', () => {
            clearTimeout(timerId);
            remainingMs -= (Date.now() - startTime);
            if (remainingMs < 1000) remainingMs = 1000;
        });

        toast.addEventListener('mouseleave', () => {
            startTime = Date.now();
            timerId = setTimeout(() => {
                dismissToastEl(toast);
            }, remainingMs);
            toast._timer = timerId;
        });
        toast._timer = timerId;

        while (container.children.length >= 4) {
            dismissToastEl(container.firstElementChild);
        }

        container.appendChild(toast);
    }
    window.showRealtimeToast = showRealtimeToast;

    window.dismissToast = function(event, btn) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }
        const toast = btn.closest('.rt-toast');
        if (toast) dismissToastEl(toast);
    };

    function dismissToastEl(toast) {
        if (!toast || toast._dismissing) return;
        toast._dismissing = true;
        if (toast._timer) clearTimeout(toast._timer);
        toast.classList.add('rt-toast-hiding');
        setTimeout(() => {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 260);
    }

    window.navigateToChat = function(accountId, clientId, clientDisplayName) {
        if (!accountId || !clientId) return;

        showDashPanel('chats');

        if (String(currentAccountId) !== String(accountId)) {
            const acctItem = document.querySelector(`.account-list-item[data-account-id="${accountId}"]`);
            if (acctItem) {
                selectAccount(acctItem);
            } else {
                currentAccountId = accountId;
                loadContacts(accountId);
            }
        }

        openChat(accountId, clientId, clientDisplayName || '');

        setTimeout(() => {
            const comp = document.getElementById('msgComposer');
            if (comp) comp.focus();
        }, 300);
    };

    function syncContactOnMessage(msg, isCurrentChat) {
        if (!msg || !msg.client_id) return;

        const isClientMsg = msg.sender_type === 'client';
        let contact = allContacts.find(c => String(c.id) === String(msg.client_id));

        if (contact) {
            contact.last_message = msg.message_body || '';
            contact.last_message_type = msg.message_type || 'text';
            contact.last_media_name = msg.media_name || '';
            contact.last_message_at = msg.created_at || new Date().toISOString();
            contact.last_sender_type = msg.sender_type;
            if (!isCurrentChat && isClientMsg) {
                contact.unread_count = (parseInt(contact.unread_count) || 0) + 1;
            }

            const idx = allContacts.indexOf(contact);
            if (idx > 0) {
                allContacts.splice(idx, 1);
                allContacts.unshift(contact);
            }
        } else {
            const clientData = msg.client || {};
            contact = {
                id: msg.client_id,
                account_id: msg.account_id,
                identifier: clientData.identifier || String(msg.client_id),
                display_name: clientData.display_name || clientData.first_name || '',
                first_name: clientData.first_name || '',
                last_name: clientData.last_name || '',
                username: clientData.username || '',
                last_message: msg.message_body || '',
                last_message_type: msg.message_type || 'text',
                last_media_name: msg.media_name || '',
                last_message_at: msg.created_at || new Date().toISOString(),
                last_sender_type: msg.sender_type,
                unread_count: (!isCurrentChat && isClientMsg) ? 1 : 0
            };
            allContacts.unshift(contact);
        }

        applyContactFilter();
    }

    // ═══════════════════════ SCROLL ═══════════════════════
    function scrollToBottom(instant) {
        const area = document.getElementById('messagesArea');
        area.scrollTo({ top: area.scrollHeight, behavior: instant ? 'instant' : 'smooth' });
        isAtBottom = true;
        newMsgCount = 0;
        const btn = document.getElementById('scrollBtn');
        btn.style.display = 'none';
        const pill = btn.querySelector('.unread-pill');
        if (pill) pill.remove();
    }
    window.scrollToBottom = scrollToBottom;

    window.handleScroll = function() {
        const area = document.getElementById('messagesArea');
        const threshold = 120;
        isAtBottom = area.scrollHeight - area.scrollTop - area.clientHeight < threshold;
        const btn = document.getElementById('scrollBtn');
        if (isAtBottom) {
            btn.style.display = 'none';
            newMsgCount = 0;
            const pill = btn.querySelector('.unread-pill');
            if (pill) pill.remove();
        }
    };

    // ═══════════════════════ HELPERS ═══════════════════════
    function formatPhone(num) {
        const digits = (num || '').replace(/\D/g, '');
        if (digits.length >= 10) {
            return '+' + digits;
        }
        return num;
    }

    function formatTime(isoStr) {
        if (!isoStr) return '';
        const d = new Date(isoStr);
        const now = new Date();
        const diff = now - d;
        if (diff < 60000) return 'now';
        if (diff < 86400000) return d.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit',hour12:false});
        if (diff < 604800000) return d.toLocaleDateString([], {weekday:'short'});
        return d.toLocaleDateString([], {day:'2-digit',month:'2-digit',year:'2-digit'});
    }

    function formatTimeShort(d) {
        return d.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit',hour12:false});
    }

    function formatDateLabel(d) {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const yesterday = new Date(today - 86400000);
        const msgDay = new Date(d.getFullYear(), d.getMonth(), d.getDate());
        if (msgDay.getTime() === today.getTime()) return 'Today';
        if (msgDay.getTime() === yesterday.getTime()) return 'Yesterday';
        return d.toLocaleDateString([], {day:'numeric',month:'long',year:'numeric'});
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;')
            .replace(/\n/g,'<br>');
    }

    // Attribute-safe escape: unlike escHtml this keeps newlines literal and
    // also escapes single quotes, since URLs land inside onclick='...'.
    function escAttr(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;')
            .replace(/'/g,'&#39;');
    }

    // Emoji button stub
    window.toggleEmoji = function() {};
    window.toggleMsgSearch = function() {};

})();
</script>

@endsection