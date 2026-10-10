<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\TelegramService;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session as FacadesSession;

class SessionController extends Controller
{
    public function index(Request $request, WhatsAppService $whatsapp, TelegramService $telegram)
    {
        $accounts = Account::all();

        // The engines are separate processes; if one is down the page must still
        // render so the admin can see account state and restart the session.
        $engineErrors = [];

        $waEngineStatuses = [];
        try {
            $waSessions = $whatsapp->listSessions();
            foreach ($waSessions as $session) {
                $aid = (string) ($session['id'] ?? $session['accountId'] ?? '');
                if ($aid !== '') {
                    $waEngineStatuses[$aid] = $session['status'] ?? 'unknown';
                }
            }
        } catch (\Throwable $e) {
            $waSessions = [];
            $engineErrors[] = 'WhatsApp engine is unreachable (port 3001).';
            Log::warning('WA engine listSessions failed', ['error' => $e->getMessage()]);
        }

        $tgEngineStatuses = [];
        try {
            $tgSessions = $telegram->listSessions();
            foreach ($tgSessions as $session) {
                $aid = (string) ($session['id'] ?? $session['accountId'] ?? '');
                if ($aid !== '') {
                    $tgEngineStatuses[$aid] = $session['status'] ?? 'unknown';
                }
            }
        } catch (\Throwable $e) {
            $tgSessions = [];
            $engineErrors[] = 'Telegram engine is unreachable (port 3002).';
            Log::warning('TG engine listSessions failed', ['error' => $e->getMessage()]);
        }

        return view('admin.sessions', compact(
            'accounts',
            'waSessions',
            'tgSessions',
            'engineErrors',
            'waEngineStatuses',
            'tgEngineStatuses'
        ));
    }

    public function syncStatus(WhatsAppService $whatsapp, TelegramService $telegram)
    {
        $synced = 0;

        Account::where('platform', 'whatsapp')
            ->whereIn('status', ['active', 'connecting'])
            ->each(function ($account) use ($whatsapp, &$synced) {
                try {
                    $engineStatus = $whatsapp->getStatus((string) $account->id);
                    $dbStatus = match($engineStatus) {
                        'active', 'connected', 'open' => 'active',
                        'connecting', 'qr' => 'connecting',
                        default => 'disconnected',
                    };
                    if ($account->status !== $dbStatus) {
                        $account->update(['status' => $dbStatus]);
                        $synced++;
                    }
                } catch (\Throwable $e) {
                    if ($account->status !== 'disconnected') {
                        $account->update(['status' => 'disconnected']);
                        $synced++;
                    }
                }
            });

        Account::where('platform', 'telegram')
            ->whereIn('status', ['active', 'connecting'])
            ->each(function ($account) use ($telegram, &$synced) {
                try {
                    $engineStatus = $telegram->getStatus((string) $account->id);
                    $dbStatus = ($engineStatus === 'active') ? 'active' : 'disconnected';
                    if ($account->status !== $dbStatus) {
                        $account->update(['status' => $dbStatus]);
                        $synced++;
                    }
                } catch (\Throwable $e) {
                    if ($account->status !== 'disconnected') {
                        $account->update(['status' => 'disconnected']);
                        $synced++;
                    }
                }
            });

        return back()->with('success', "Status sync complete. {$synced} account(s) updated.");
    }

    public function waStartSession(Request $request, WhatsAppService $whatsapp): RedirectResponse
    {
        $request->validate([
            'phone_number' => 'required|string',
        ]);

        $phone = $request->input('phone_number');

        // Check if this number already has an active WhatsApp session
        $existing = Account::where('platform', 'whatsapp')
            ->where('phone_number', $phone)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            // Verify it's actually live in the engine
            try {
                $status = $whatsapp->getStatus($existing->id);
                if ($status === 'active') {
                    return back()->with('error', 'WhatsApp is already logged in with ' . $phone . ' (Account ID ' . $existing->id . '). Disconnect first if you want to re-login.');
                }
            } catch (\Throwable $e) {
                // Engine unreachable — fall through to create new session
            }
        }

        $account = Account::updateOrCreate(
            ['platform' => 'whatsapp', 'phone_number' => $phone],
            ['account_name' => $phone, 'status' => 'connecting']
        );

        try {
            $whatsapp->startSession($account->id, $phone);
        } catch (\Throwable $e) {
            Log::warning('WA engine startSession failed', ['error' => $e->getMessage()]);
            $account->update(['status' => 'disconnected']);
            return back()->with('error', 'WhatsApp engine is unreachable (port 3001). Restart it with pm2 restart whatsapp-engine.');
        }

        return back()->with('success', 'QR generation started')->with('wa_account_id', $account->id);
    }

    public function waStartPairing(Request $request, WhatsAppService $whatsapp): RedirectResponse
    {
        $request->validate([
            'phone_number' => 'required|string',
        ]);

        $phone = $request->input('phone_number');

        // Check if this number already has an active WhatsApp session
        $existing = Account::where('platform', 'whatsapp')
            ->where('phone_number', $phone)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            try {
                $status = $whatsapp->getStatus($existing->id);
                if ($status === 'active') {
                    return back()->with('error', 'WhatsApp is already logged in with ' . $phone . ' (Account ID ' . $existing->id . '). Disconnect first if you want to re-login.');
                }
            } catch (\Throwable $e) {
                // Engine unreachable — fall through
            }
        }

        $account = Account::updateOrCreate(
            ['platform' => 'whatsapp', 'phone_number' => $phone],
            ['account_name' => $phone, 'status' => 'connecting']
        );

        try {
            $whatsapp->startPairing($account->id, $phone);
        } catch (\Throwable $e) {
            Log::warning('WA engine startPairing failed', ['error' => $e->getMessage()]);
            $account->update(['status' => 'disconnected']);
            return back()->with('error', 'WhatsApp engine is unreachable (port 3001). Restart it with pm2 restart whatsapp-engine.');
        }

        return back()->with('success', 'Pairing code sent. Check your WhatsApp → Settings → Linked Devices → Link with phone number.')->with('wa_pairing_id', $account->id);
    }

    public function waCheckPairing(Request $request, WhatsAppService $whatsapp)
    {
        $accountId = $request->query('account_id');
        if (!$accountId) {
            return response()->json(['status' => 'not_found']);
        }

        $status = $whatsapp->getPairingStatus($accountId);

        return response()->json([
            'status' => $status['status'] ?? 'not_found',
            'mode' => $status['mode'] ?? 'qr',
            'code' => $status['pairingCode'] ?? $status['code'] ?? null,
            'pairingCode' => $status['pairingCode'] ?? $status['code'] ?? null,
        ]);
    }

    public function waGetQr(Request $request, string $accountId, WhatsAppService $whatsapp)
    {
        $response = $whatsapp->getQrCode($accountId);

        if ($response->successful() && str_contains($response->header('content-type', ''), 'image')) {
            // Return raw image bytes with proper headers for browser display
            return response($response->body(), 200)
                ->header('Content-Type', 'image/png')
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
                ->header('Pragma', 'no-cache');
        }

        // Return a 1x1 transparent PNG placeholder so the frontend doesn't break
        // and keeps polling. The JS will keep retrying until a real QR arrives.
        $transparent = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVQI12NgAAIABQABNjN9GQAAAAlwSFlzAAAWJQAAFiUBSVIk8AAAAA0lEQVQI12P4z8BQDwAEgAF/QualIQAAAABJRU5ErkJggg=='
        );
        return response($transparent, 200)
            ->header('Content-Type', 'image/png')
            ->header('X-QR-Status', 'waiting');
    }

    public function tgRequestOtp(Request $request, TelegramService $telegram): RedirectResponse
    {
        $request->validate([
            'phone_number' => 'required|string',
        ]);

        $phone = $request->input('phone_number');

        // Check if this number already has an active Telegram session
        $existing = Account::where('platform', 'telegram')
            ->where('phone_number', $phone)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            try {
                $status = $telegram->getStatus($existing->id);
                if ($status === 'active') {
                    return back()->with('error', 'Telegram is already logged in with ' . $phone . ' (Account ID ' . $existing->id . '). Disconnect first if you want to re-login.');
                }
            } catch (\Throwable $e) {
                // Engine unreachable — fall through
            }
        }

        $account = Account::updateOrCreate(
            [
                'platform' => 'telegram',
                'phone_number' => $phone,
            ],
            [
                'account_name' => $phone,
                'status' => 'disconnected',
            ]
        );

        try {
            $telegram->requestOtp($account->id, $phone);
        } catch (\Throwable $e) {
            Log::warning('TG engine requestOtp failed', ['error' => $e->getMessage()]);
            $account->update(['status' => 'disconnected']);
            return back()->with('error', 'Telegram engine is unreachable (port 3002). Restart it with pm2 restart telegram-engine.');
        }

        $account->update(['status' => 'connecting']);
        FacadesSession::put('tg_pending_account', $account->id);

        return back()->with('success', 'OTP sent');
    }

    public function tgVerifyOtp(Request $request, TelegramService $telegram): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string',
            'phone2fa' => 'nullable|string',
        ]);

        $accountId = FacadesSession::get('tg_pending_account');

        if (!$accountId) {
            return back()->with('error', 'No pending Telegram session found. Please enter your phone number and request OTP again.');
        }

        try {
            $result = $telegram->verifyOtp(
                (string) $accountId,
                $request->input('code'),
                $request->filled('phone2fa') ? $request->input('phone2fa') : null
            );

            if (($result['status'] ?? '') === 'error') {
                if (!empty($result['need_2fa'])) {
                    return back()->with('error', '2FA password is required. Please fill in the 2FA Password field.');
                }
                return back()->with('error', 'Telegram verification failed: ' . ($result['message'] ?? 'Invalid code'));
            }

            Account::find($accountId)?->update(['status' => 'active']);

            FacadesSession::forget('tg_pending_account');

            return back()->with('success', 'Telegram account connected successfully');
        } catch (\Throwable $e) {
            Log::warning('TG engine verifyOtp error', ['error' => $e->getMessage()]);
            return back()->with('error', 'Failed to communicate with Telegram engine: ' . $e->getMessage());
        }
    }

    public function disconnectAccount(Request $request, Account $account): RedirectResponse
    {
        if ($account->platform === 'whatsapp') {
            app(WhatsAppService::class)->disconnectSession($account->id);
        }

        if ($account->platform === 'telegram') {
            app(TelegramService::class)->disconnectSession($account->id);
        }

        $account->update(['status' => 'disconnected']);

        return back()->with('success', 'Account disconnected');
    }

    public function deleteAccount(Request $request, Account $account): RedirectResponse
    {
        if ($account->platform === 'whatsapp') {
            app(WhatsAppService::class)->disconnectSession($account->id);
        }

        if ($account->platform === 'telegram') {
            app(TelegramService::class)->disconnectSession($account->id);
        }

        $account->delete();

        return back()->with('success', 'Account deleted');
    }
}
