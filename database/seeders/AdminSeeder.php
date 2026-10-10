<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Default Admin Account
        User::updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        // Demo Staff Accounts
        User::firstOrCreate(
            ['email' => 'staff1@test.com'],
            [
                'name' => 'Staff 1',
                'password' => Hash::make('staff123'),
                'role' => 'staff',
                'status' => 'active',
            ]
        );

        User::firstOrCreate(
            ['email' => 'staff2@test.com'],
            [
                'name' => 'Staff 2',
                'password' => Hash::make('staff123'),
                'role' => 'staff',
                'status' => 'active',
            ]
        );
    }
}
