<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PDO;

/**
 * Web installer back-end.
 *
 * The wizard is reachable only while the app is NOT installed
 * (see App\Http\Middleware\EnsureNotInstalled). "Installed" is a
 * dual gate: an explicit lock file, OR a fully bootstrapped app
 * (APP_KEY set + database reachable + at least one user row), so
 * an existing deployment can never be re-installed by accident.
 */
class InstallerService
{
    public function lockPath(): string
    {
        return storage_path('app/installed.lock');
    }

    public function isInstalled(): bool
    {
        if (file_exists($this->lockPath())) {
            return true;
        }

        if ((string) env('APP_KEY', '') === '') {
            return false;
        }

        try {
            DB::connection()->getPdo();

            if (!\Illuminate\Support\Facades\Schema::hasTable('users')) {
                return false;
            }

            return DB::table('users')->count() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Server requirement checks.
     *
     * @return array<int, array{label: string, ok: bool, required: bool, current: string}>
     */
    public function requirements(): array
    {
        $checks = [];

        $checks[] = [
            'label' => 'PHP version >= 8.3.0',
            'ok' => version_compare(PHP_VERSION, '8.3.0', '>='),
            'required' => true,
            'current' => PHP_VERSION,
        ];

        foreach (['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'curl', 'bcmath', 'fileinfo'] as $ext) {
            $checks[] = [
                'label' => "PHP extension: {$ext}",
                'ok' => extension_loaded($ext),
                'required' => true,
                'current' => extension_loaded($ext) ? 'installed' : 'missing',
            ];
        }

        $drivers = class_exists(PDO::class) ? PDO::getAvailableDrivers() : [];
        $checks[] = [
            'label' => 'PDO MySQL driver',
            'ok' => in_array('mysql', $drivers, true),
            'required' => true,
            'current' => $drivers === [] ? 'no PDO drivers' : implode(', ', $drivers),
        ];

        $autoload = base_path('vendor/autoload.php');
        $checks[] = [
            'label' => 'Composer dependencies installed (vendor/autoload.php)',
            'ok' => file_exists($autoload),
            'required' => true,
            'current' => file_exists($autoload) ? 'present — run `composer install` if this is missing' : 'missing — run `composer install` first',
        ];

        $writablePaths = [
            'storage/' => storage_path(),
            'storage/app/' => storage_path('app'),
            'storage/framework/cache/' => storage_path('framework/cache'),
            'storage/framework/sessions/' => storage_path('framework/sessions'),
            'storage/framework/views/' => storage_path('framework/views'),
            'storage/logs/' => storage_path('logs'),
            'bootstrap/cache/' => base_path('bootstrap/cache'),
        ];
        foreach ($writablePaths as $label => $path) {
            $checks[] = [
                'label' => "Writable: {$label}",
                'ok' => is_dir($path) && is_writable($path),
                'required' => true,
                'current' => !is_dir($path) ? 'directory missing' : (is_writable($path) ? 'writable' : 'not writable'),
            ];
        }

        $envPath = base_path('.env');
        $envWritable = file_exists($envPath) ? is_writable($envPath) : is_writable(base_path());
        $checks[] = [
            'label' => 'Writable: .env file (or project root, to create it)',
            'ok' => $envWritable,
            'required' => true,
            'current' => file_exists($envPath)
                ? (is_writable($envPath) ? '.env writable' : '.env not writable')
                : (is_writable(base_path()) ? '.env will be created from .env.example' : 'project root not writable'),
        ];

        // ── Soft checks (warnings only) ──────────────────────────────
        $redisExt = extension_loaded('redis');
        $predis = is_dir(base_path('vendor/predis'));
        $checks[] = [
            'label' => 'Redis client (phpredis extension or Predis package)',
            'ok' => $redisExt || $predis,
            'required' => false,
            'current' => $redisExt ? 'phpredis extension' : ($predis ? 'Predis package' : 'none found — cache/queue need Redis'),
        ];

        $redisUp = $this->tcpReachable('127.0.0.1', 6379);
        $checks[] = [
            'label' => 'Redis server reachable (127.0.0.1:6379)',
            'ok' => $redisUp,
            'required' => false,
            'current' => $redisUp ? 'reachable' : 'not reachable — start Redis before running queues',
        ];

        $node = $this->binaryVersion('node', ['-v']);
        $checks[] = [
            'label' => 'Node.js binary (WhatsApp engine)',
            'ok' => $node !== null,
            'required' => false,
            'current' => $node ?? 'not found in PATH',
        ];

        $python = $this->binaryVersion('python3', ['--version']);
        $checks[] = [
            'label' => 'Python 3 binary (Telegram engine)',
            'ok' => $python !== null,
            'required' => false,
            'current' => $python ?? 'not found in PATH',
        ];

        return $checks;
    }

    public function hasHardFailure(array $requirements): bool
    {
        foreach ($requirements as $check) {
            if ($check['required'] && !$check['ok']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{host: string, port: int|string, database: string, username: string, password?: string}  $cfg
     * @return array{ok: bool, error?: string}
     */
    public function testDatabase(array $cfg): array
    {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s', $cfg['host'], (int) $cfg['port'], $cfg['database']);
            $pdo = new PDO($dsn, (string) $cfg['username'], (string) ($cfg['password'] ?? ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $pdo = null;

            return ['ok' => true];
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            // Never let a credential leak back into the UI via an error string.
            if (!empty($cfg['password'])) {
                $message = str_replace((string) $cfg['password'], '***', $message);
            }

            return ['ok' => false, 'error' => $message];
        }
    }

    public function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    public function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Build the canonical .env value map from the wizard's stored parts.
     *
     * @param  array<string, mixed>  $db   Database step values.
     * @param  array<string, mixed>  $keys Keys & services step values.
     * @return array<string, string>
     */
    public function envValues(array $db, array $keys): array
    {
        $appUrl = rtrim((string) ($db['app_url'] ?? ''), '/');
        $appHost = parse_url($appUrl, PHP_URL_HOST) ?: '127.0.0.1';
        $isHttps = str_starts_with(strtolower($appUrl), 'https://');
        $redisPassword = (string) ($keys['redis_password'] ?? '');

        return [
            'APP_NAME' => (string) ($db['app_name'] ?? 'Social CRM'),
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $appUrl,
            'APP_KEY' => (string) $keys['app_key'],
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) $db['db_host'],
            'DB_PORT' => (string) $db['db_port'],
            'DB_DATABASE' => (string) $db['db_database'],
            'DB_USERNAME' => (string) $db['db_username'],
            'DB_PASSWORD' => (string) ($db['db_password'] ?? ''),
            'SESSION_DRIVER' => 'file',
            'SESSION_ENCRYPT' => 'true',
            'SESSION_SECURE_COOKIE' => $isHttps ? 'true' : 'false',
            'BROADCAST_CONNECTION' => 'reverb',
            'QUEUE_CONNECTION' => 'redis',
            'CACHE_STORE' => 'redis',
            'REDIS_HOST' => (string) ($keys['redis_host'] ?? '127.0.0.1'),
            'REDIS_PORT' => (string) ($keys['redis_port'] ?? 6379),
            // An empty password is written as the literal `null`, matching
            // .env.example: Laravel's env() turns the unquoted literal into
            // a real null, so Redis clients skip AUTH entirely.
            'REDIS_PASSWORD' => $redisPassword === '' ? 'null' : $redisPassword,
            'INTERNAL_API_SECRET' => (string) $keys['internal_secret'],
            'REVERB_APP_ID' => (string) $keys['reverb_app_id'],
            'REVERB_APP_KEY' => (string) $keys['reverb_app_key'],
            'REVERB_APP_SECRET' => (string) $keys['reverb_app_secret'],
            'REVERB_HOST' => '127.0.0.1',
            'REVERB_PORT' => '8080',
            'REVERB_SCHEME' => 'http',
            'REVERB_ALLOWED_ORIGIN' => $appHost,
            'VITE_REVERB_APP_KEY' => (string) $keys['reverb_app_key'],
            'VITE_REVERB_HOST' => '127.0.0.1',
            'VITE_REVERB_PORT' => '8080',
            'VITE_REVERB_SCHEME' => 'http',
            'WA_ENGINE_URL' => 'http://127.0.0.1:3001',
            'TG_ENGINE_URL' => 'http://127.0.0.1:3002',
        ];
    }

    /**
     * Write values into .env (created from .env.example when missing).
     * The previous file is backed up to .env.bak-<YmdHis> and the new
     * file is swapped in atomically with 0600 permissions.
     *
     * @param  array<string, string>  $values
     */
    public function writeEnv(array $values): void
    {
        $envPath = base_path('.env');

        if (file_exists($envPath)) {
            @copy($envPath, base_path('.env.bak-' . date('YmdHis')));
            $content = (string) file_get_contents($envPath);
        } elseif (file_exists(base_path('.env.example'))) {
            $content = (string) file_get_contents(base_path('.env.example'));
        } else {
            $content = '';
        }

        $lines = $content === '' ? [] : (preg_split('/\r\n|\r|\n/', $content) ?: []);
        $seen = [];

        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=/', (string) $line, $m)) {
                $key = $m[1];
                if (array_key_exists($key, $values) && !isset($seen[$key])) {
                    $lines[$i] = $key . '=' . $this->formatEnvValue($values[$key]);
                    $seen[$key] = true;
                }
            }
        }

        foreach ($values as $key => $value) {
            if (!isset($seen[$key])) {
                $lines[] = $key . '=' . $this->formatEnvValue($value);
            }
        }

        $out = implode("\n", $lines);
        if (!str_ends_with($out, "\n")) {
            $out .= "\n";
        }

        $tmp = $envPath . '.tmp-' . getmypid();
        file_put_contents($tmp, $out);
        @chmod($tmp, 0600);
        rename($tmp, $envPath);
        @chmod($envPath, 0600);
    }

    /**
     * Run migrations + seeders against the just-written .env values,
     * create the admin account and drop the installed lock file.
     *
     * @param  array{name: string, email: string, password: string}  $admin
     */
    public function runSetup(array $admin): void
    {
        $env = $this->readEnvValues();

        if (!empty($env['APP_KEY'])) {
            Config::set('app.key', $env['APP_KEY']);
        }

        // Point the runtime at the database the operator just configured —
        // the values loaded at bootstrap still describe the pre-install .env.
        Config::set('database.default', 'mysql');
        $connection = (array) Config::get('database.connections.mysql', []);
        Config::set('database.connections.mysql', array_merge($connection, [
            'host' => $env['DB_HOST'] ?? '127.0.0.1',
            'port' => $env['DB_PORT'] ?? '3306',
            'database' => $env['DB_DATABASE'] ?? '',
            'username' => $env['DB_USERNAME'] ?? '',
            'password' => $env['DB_PASSWORD'] ?? '',
        ]));
        DB::purge('mysql');
        DB::reconnect('mysql');

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', [
            '--class' => \Database\Seeders\SystemSettingsSeeder::class,
            '--force' => true,
        ]);

        User::updateOrCreate(
            ['email' => $admin['email']],
            [
                'name' => $admin['name'],
                'password' => Hash::make($admin['password']),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        // The PM2 engines read INTERNAL_API_SECRET from the process
        // environment (see ecosystem.config.cjs). This helper lets the
        // operator export the exact same secret the wizard generated:
        //   source storage/app/installer/engine-env.sh
        //   pm2 start ecosystem.config.cjs
        $installerDir = storage_path('app/installer');
        if (!is_dir($installerDir)) {
            mkdir($installerDir, 0755, true);
        }
        $secret = (string) ($env['INTERNAL_API_SECRET'] ?? '');
        $helper = "# Generated by the Social CRM installer — do not commit this file.\n"
            . "# Source it before `pm2 start ecosystem.config.cjs` so the WhatsApp\n"
            . "# and Telegram engines receive the same secret as Laravel's .env.\n"
            . "export INTERNAL_API_SECRET='" . str_replace("'", "'\\''", $secret) . "'\n";
        file_put_contents($installerDir . '/engine-env.sh', $helper);
        @chmod($installerDir . '/engine-env.sh', 0600);

        // The lock is written LAST: any failure above leaves the wizard
        // open so the operator can fix the problem and retry.
        file_put_contents($this->lockPath(), json_encode([
            'installed_at' => now()->toIso8601String(),
            'app_url' => $env['APP_URL'] ?? '',
            'via' => 'web-installer',
        ], JSON_PRETTY_PRINT) . "\n");

        Artisan::call('config:clear');
    }

    private function formatEnvValue(string $value): string
    {
        $value = str_replace(["\r", "\n"], '', $value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/[\s#"\']/', $value)) {
            return '"' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value) . '"';
        }

        return $value;
    }

    /** @return array<string, string> */
    private function readEnvValues(): array
    {
        $path = base_path('.env');
        $values = [];

        if (!file_exists($path)) {
            return $values;
        }

        foreach ((preg_split('/\r\n|\r|\n/', (string) file_get_contents($path)) ?: []) as $line) {
            if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', (string) $line, $m)) {
                $raw = trim($m[2]);
                if (strlen($raw) >= 2 && $raw[0] === '"' && str_ends_with($raw, '"')) {
                    $raw = stripcslashes(substr($raw, 1, -1));
                } elseif (strlen($raw) >= 2 && $raw[0] === "'" && str_ends_with($raw, "'")) {
                    $raw = substr($raw, 1, -1);
                }
                $values[$m[1]] = $raw;
            }
        }

        return $values;
    }

    private function tcpReachable(string $host, int $port): bool
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, 1);
        if ($socket === false) {
            return false;
        }
        fclose($socket);

        return true;
    }

    private function binaryVersion(string $binary, array $versionArgs): ?string
    {
        // $binary only ever comes from this class ('node' / 'python3' literals).
        $path = null;

        if (function_exists('exec')) {
            try {
                $out = [];
                $code = 1;
                @exec('which ' . $binary . ' 2>/dev/null', $out, $code);
                if ($code === 0 && !empty($out[0])) {
                    $path = trim($out[0]);
                }
            } catch (\Throwable) {
                $path = null;
            }
        }

        if ($path === null) {
            foreach (['/usr/bin/', '/usr/local/bin/', '/opt/homebrew/bin/', '/bin/'] as $dir) {
                if (is_executable($dir . $binary)) {
                    $path = $dir . $binary;
                    break;
                }
            }
        }

        if ($path === null) {
            return null;
        }

        if (function_exists('exec')) {
            try {
                $out = [];
                $code = 1;
                @exec(escapeshellarg($path) . ' ' . implode(' ', $versionArgs) . ' 2>&1', $out, $code);
                if ($code === 0 && !empty($out[0])) {
                    return trim($out[0]);
                }
            } catch (\Throwable) {
                // Fall through — the binary exists even if --version failed.
            }
        }

        return $path;
    }
}
