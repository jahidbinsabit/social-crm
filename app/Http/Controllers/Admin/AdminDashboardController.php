<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_accounts'     => \App\Models\Account::count(),
            'active_accounts'    => \App\Models\Account::where('status', 'active')->count(),
            'total_staff'        => \App\Models\User::where('role', 'staff')->count(),
            'active_staff'       => \App\Models\User::where('role', 'staff')->where('status', 'active')->count(),
            'total_campaigns'    => \App\Models\Campaign::count(),
            'running_campaigns'  => \App\Models\Campaign::where('status', 'running')->count(),
            'messages_today'     => \App\Models\Message::whereDate('created_at', today())->count(),
            'unread_messages'    => \App\Models\Message::where('is_read', false)->where('sender_type', 'client')->count(),
            'clients_contacted'  => \App\Models\Client::where('status', 'contacted')->count(),
            'clients_pending'    => \App\Models\Client::where('status', 'pending')->count(),
        ];

        $recentMessages = \App\Models\Message::with('account', 'client')
            ->where('sender_type', 'client')
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentMessages'));
    }
}
