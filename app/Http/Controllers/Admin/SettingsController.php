<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting as SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = SystemSettings::current();

        return view('admin.settings', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'min_delay_seconds' => 'required|integer|min:5|max:300',
            'max_delay_seconds' => 'required|integer|min:5|max:300|gte:min_delay_seconds',
            'daily_limit_per_account' => 'required|integer|min:1|max:100',
            'proxy_ip' => 'nullable|ip',
            'proxy_port' => 'nullable|integer|between:1,65535',
            'proxy_username' => 'nullable|string',
            'proxy_password' => 'nullable|string',
        ]);

        SystemSettings::current()->update($validated);

        return back()->with('success', 'Settings saved');
    }
}
