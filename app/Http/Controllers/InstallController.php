<?php

namespace App\Http\Controllers;

use App\Services\InstallerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Multi-step web installer (/install).
 *
 * Reachable only while the app is not installed — every wizard route
 * sits behind the `not.installed` middleware. Answers are carried in
 * the session under the single key `install` between steps. NOTE:
 * that means the database password (and briefly the admin password)
 * live in the session file for the duration of the wizard; both are
 * forgotten the moment the install finishes or fails over to /login.
 * No installer input is ever written to the application log.
 */
class InstallController extends Controller
{
    public function __construct(private InstallerService $installer)
    {
    }

    // ── Step 1: requirements ─────────────────────────────────────────

    public function requirements()
    {
        $requirements = $this->installer->requirements();

        return view('install.requirements', [
            'step' => 1,
            'requirements' => $requirements,
            'hardFail' => $this->installer->hasHardFailure($requirements),
        ]);
    }

    public function requirementsNext()
    {
        $requirements = $this->installer->requirements();

        if ($this->installer->hasHardFailure($requirements)) {
            return redirect()->route('install.requirements')
                ->with('error', 'Some required checks are failing. Fix them on the server, then try again.');
        }

        return redirect()->route('install.database');
    }

    // ── Step 2: application + database ───────────────────────────────

    public function database()
    {
        $db = $this->wizard()['db'] ?? [];

        return view('install.database', [
            'step' => 2,
            'db' => $db,
            'defaultUrl' => rtrim(request()->getSchemeAndHttpHost(), '/'),
            'passwordStored' => !empty($db['password_set']),
        ]);
    }

    public function databaseNext(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'app_name' => ['required', 'string', 'max:60'],
            'app_url' => ['required', 'url', 'max:255'],
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'db_database' => ['required', 'string', 'max:64', 'regex:/^[\w-]+$/'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ]);
        if ($validator->fails()) {
            // Manual redirect so the password is never flashed back as old input.
            return redirect()->route('install.database')
                ->withErrors($validator)
                ->withInput($request->except('db_password'));
        }
        $validated = $validator->validated();

        $existing = $this->wizard()['db'] ?? [];

        // Blank password field = keep the one already stored in the wizard
        // session (it is never re-rendered into the form).
        $password = $validated['db_password'] ?? '';
        if ($password === '' && !empty($existing['password_set'])) {
            $password = (string) ($existing['db_password'] ?? '');
        }

        $test = $this->installer->testDatabase([
            'host' => $validated['db_host'],
            'port' => $validated['db_port'],
            'database' => $validated['db_database'],
            'username' => $validated['db_username'],
            'password' => $password,
        ]);

        if (!$test['ok']) {
            return redirect()->route('install.database')
                ->withErrors(['database' => 'Could not connect to MySQL: ' . ($test['error'] ?? 'unknown error')])
                ->withInput($request->except('db_password'));
        }

        $this->saveWizard(['db' => [
            'app_name' => $validated['app_name'],
            'app_url' => rtrim($validated['app_url'], '/'),
            'db_host' => $validated['db_host'],
            'db_port' => (int) $validated['db_port'],
            'db_database' => $validated['db_database'],
            'db_username' => $validated['db_username'],
            'db_password' => $password,
            'password_set' => true,
        ]]);

        return redirect()->route('install.keys');
    }

    // ── Step 3: keys & services ──────────────────────────────────────

    public function keys()
    {
        if (empty($this->wizard()['db'])) {
            return redirect()->route('install.database')
                ->with('error', 'Complete the database step first.');
        }

        $keys = $this->wizard()['keys'] ?? null;

        if ($keys === null) {
            // Auto-generate once, then keep the same values across visits
            // so the operator can safely go back and forth.
            $keys = [
                'app_key' => $this->installer->generateKey(),
                'internal_secret' => $this->installer->generateSecret(),
                'reverb_app_id' => substr($this->installer->generateSecret(), 0, 32),
                'reverb_app_key' => $this->installer->generateSecret(),
                'reverb_app_secret' => $this->installer->generateSecret(),
                'redis_host' => '127.0.0.1',
                'redis_port' => 6379,
                'redis_password' => '',
                'redis_password_set' => false,
            ];
            $this->saveWizard(['keys' => $keys]);
        }

        return view('install.keys', [
            'step' => 3,
            'keys' => $keys,
        ]);
    }

    public function keysNext(Request $request)
    {
        if (empty($this->wizard()['db'])) {
            return redirect()->route('install.database')
                ->with('error', 'Complete the database step first.');
        }

        $validator = Validator::make($request->all(), [
            'app_key' => ['required', 'string', 'regex:/^base64:[A-Za-z0-9+\/=]{44}$/'],
            'internal_secret' => ['required', 'string', 'min:32', 'max:255'],
            'reverb_app_id' => ['required', 'string', 'max:100'],
            'reverb_app_key' => ['required', 'string', 'max:255'],
            'reverb_app_secret' => ['required', 'string', 'max:255'],
            'redis_host' => ['required', 'string', 'max:255'],
            'redis_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'redis_password' => ['nullable', 'string', 'max:255'],
        ]);
        if ($validator->fails()) {
            return redirect()->route('install.keys')
                ->withErrors($validator)
                ->withInput($request->except('redis_password'));
        }
        $validated = $validator->validated();

        $existing = $this->wizard()['keys'] ?? [];

        // Same keep-if-blank rule as the database password.
        $redisPassword = $validated['redis_password'] ?? '';
        $redisPasswordSet = !empty($existing['redis_password_set']);
        if ($redisPassword === '' && $redisPasswordSet) {
            $redisPassword = (string) ($existing['redis_password'] ?? '');
        } elseif ($redisPassword !== '') {
            $redisPasswordSet = true;
        }

        $this->saveWizard(['keys' => [
            'app_key' => $validated['app_key'],
            'internal_secret' => $validated['internal_secret'],
            'reverb_app_id' => $validated['reverb_app_id'],
            'reverb_app_key' => $validated['reverb_app_key'],
            'reverb_app_secret' => $validated['reverb_app_secret'],
            'redis_host' => $validated['redis_host'],
            'redis_port' => (int) $validated['redis_port'],
            'redis_password' => $redisPassword,
            'redis_password_set' => $redisPasswordSet,
        ]]);

        return redirect()->route('install.admin');
    }

    // ── Step 4: administrator account ────────────────────────────────

    public function admin()
    {
        if (empty($this->wizard()['keys'])) {
            return redirect()->route('install.keys')
                ->with('error', 'Complete the keys step first.');
        }

        return view('install.admin', [
            'step' => 4,
            'admin' => $this->wizard()['admin'] ?? [],
        ]);
    }

    public function adminNext(Request $request)
    {
        if (empty($this->wizard()['keys'])) {
            return redirect()->route('install.keys')
                ->with('error', 'Complete the keys step first.');
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);
        if ($validator->fails()) {
            return redirect()->route('install.admin')
                ->withErrors($validator)
                ->withInput($request->except('password', 'password_confirmation'));
        }
        $validated = $validator->validated();

        $this->saveWizard(['admin' => [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]]);

        return redirect()->route('install.review');
    }

    // ── Step 5: review + install ─────────────────────────────────────

    public function review()
    {
        if (($redirect = $this->guardComplete()) !== null) {
            return $redirect;
        }

        $wizard = $this->wizard();

        return view('install.review', [
            'step' => 5,
            'db' => $wizard['db'],
            'keys' => $wizard['keys'],
            'admin' => $wizard['admin'],
        ]);
    }

    public function finish()
    {
        if (($redirect = $this->guardComplete()) !== null) {
            return $redirect;
        }

        $wizard = $this->wizard();

        try {
            $values = $this->installer->envValues($wizard['db'], $wizard['keys']);
            $this->installer->writeEnv($values);
            $this->installer->runSetup($wizard['admin']);
        } catch (\Throwable $e) {
            // Installation did NOT complete — no lock file was written, so
            // the wizard stays open and the operator can retry. The message
            // is scrubbed of the database password before it is shown.
            $message = $e->getMessage();
            $dbPassword = (string) ($wizard['db']['db_password'] ?? '');
            if ($dbPassword !== '') {
                $message = str_replace($dbPassword, '***', $message);
            }

            return redirect()->route('install.review')
                ->with('error', 'Installation failed: ' . $message . ' — the .env file may already have been written, but the install is NOT complete. Fix the problem and press Install again.');
        }

        // Secrets must not outlive the wizard in the session store.
        session()->forget('install');

        return redirect()->route('install.done');
    }

    // ── Done (outside the not.installed gate — see routes/web.php) ────

    public function done()
    {
        if (!$this->installer->isInstalled()) {
            return redirect()->route('install.requirements');
        }

        return view('install.done', [
            'step' => 6,
            'basePath' => base_path(),
            'appUrl' => (string) config('app.url', ''),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function wizard(): array
    {
        return (array) session('install', []);
    }

    private function saveWizard(array $patch): void
    {
        session(['install' => array_merge($this->wizard(), $patch)]);
    }

    private function guardComplete()
    {
        $wizard = $this->wizard();

        if (empty($wizard['db'])) {
            return redirect()->route('install.database')
                ->with('error', 'Complete the database step first.');
        }
        if (empty($wizard['keys'])) {
            return redirect()->route('install.keys')
                ->with('error', 'Complete the keys step first.');
        }
        if (empty($wizard['admin'])) {
            return redirect()->route('install.admin')
                ->with('error', 'Complete the administrator step first.');
        }

        return null;
    }
}
