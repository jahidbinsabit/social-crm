<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Admin account — firstOrCreate only. Re-running the seeder must
        // NEVER reset an existing admin's password (the old updateOrCreate
        // silently reverted it to a known default on every re-seed).
        $adminExists = User::where('email', 'admin@test.com')->exists();

        if (!$adminExists) {
            $adminPassword = env('ADMIN_PASSWORD') ?: Str::random(24);

            User::firstOrCreate(
                ['email' => 'admin@test.com'],
                [
                    'name' => 'Admin',
                    'password' => Hash::make($adminPassword),
                    'role' => 'admin',
                    'status' => 'active',
                ]
            );

            if (!env('ADMIN_PASSWORD')) {
                // Printed exactly once, at creation time only. Save it in a
                // password manager — it is not stored anywhere in plaintext.
                $this->command?->warn('==================================================');
                $this->command?->warn(' Admin account created: admin@test.com');
                $this->command?->warn(' Generated admin password (shown ONCE): ' . $adminPassword);
                $this->command?->warn(' Set ADMIN_PASSWORD in .env to choose it yourself.');
                $this->command?->warn('==================================================');
            }
        }

        // Demo staff accounts — local development only, and only on explicit
        // opt-in. Never seed known-credential staff into production.
        if (app()->environment('local') && env('SEED_DEMO_STAFF', false)) {
            $staffPassword = env('DEMO_STAFF_PASSWORD') ?: Str::random(24);
            $created = 0;

            foreach (['staff1@test.com' => 'Staff 1', 'staff2@test.com' => 'Staff 2'] as $email => $name) {
                $staff = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => Hash::make($staffPassword),
                        'role' => 'staff',
                        'status' => 'active',
                    ]
                );

                if ($staff->wasRecentlyCreated) {
                    $created++;
                }
            }

            if ($created > 0 && !env('DEMO_STAFF_PASSWORD')) {
                $this->command?->warn(' Demo staff seeded (local only). Generated staff password (shown ONCE): ' . $staffPassword);
            }
        }
    }
}
