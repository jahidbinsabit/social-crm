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

        // Contract with the settings blade (cross-worker note): the view
        // receives $proxyPasswordSet (bool) and must render the password
        // field EMPTY with a "password is set" hint — the stored value is
        // never sent back to the browser. The blade at audit time rendered
        // $settings->proxy_password into the input value; with the model's
        // new $hidden + encrypted cast that also no longer round-trips.
        $proxyPasswordSet = !empty($settings->proxy_password);

        return view('admin.settings', compact('settings', 'proxyPasswordSet'));
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

        // An empty submission means "keep the current password" — submitting
        // the settings form must never silently wipe a stored secret.
        if (empty($validated['proxy_password'])) {
            unset($validated['proxy_password']);
        }

        SystemSettings::current()->update($validated);

        return back()->with('success', 'Settings saved');
    }
}
