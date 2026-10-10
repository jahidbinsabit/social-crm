<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        SystemSetting::create([
            'min_delay_seconds' => 20,
            'max_delay_seconds' => 45,
            'daily_limit_per_account' => 25,
        ]);
    }
}
