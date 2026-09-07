<?php

namespace Database\Seeders;

use App\Models\PlatformAdmin;
use Database\Seeders\Support\DemoGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * PlatformAdminSeeder — akun Super-Admin DEMO (PRD 5.4).
 * Khusus development/staging; diblokir di production (temuan2.md P1).
 * Admin production dibuat lewat ProductionKernelSeeder + env PLATFORM_ADMIN_*.
 */
class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        DemoGuard::assertNonProduction('PlatformAdminSeeder');

        PlatformAdmin::updateOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'full_name' => 'Super Administrator',
                'password' => Hash::make(DemoGuard::DEMO_PASSWORD),
                'is_active' => true,
            ]
        );

    }
}
