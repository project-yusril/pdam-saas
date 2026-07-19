<?php

namespace Database\Seeders;

use App\Models\PlatformAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * PlatformAdminSeeder — akun Super-Admin awal (PRD 5.4).
 * Ganti password default segera setelah instalasi.
 */
class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        PlatformAdmin::updateOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'full_name' => 'Super Administrator',
                'password' => Hash::make('12345678'),
                'is_active' => true,
            ]
        );

    }
}
