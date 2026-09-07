<?php

namespace Database\Seeders;

use App\Models\PlatformAdmin;
use Database\Seeders\Support\DemoGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * ProductionKernelSeeder — satu-satunya jalur seed yang boleh dijalankan di
 * production (temuan2.md P1). Hanya katalog platform (modul, role template,
 * permission). TIDAK membuat tenant demo, user demo, atau password 12345678.
 *
 * Akun super admin awal dibuat opsional lewat environment:
 *   PLATFORM_ADMIN_EMAIL, PLATFORM_ADMIN_NAME, PLATFORM_ADMIN_PASSWORD
 * Password weak/demo ditolak oleh DemoGuard.
 */
class ProductionKernelSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            ModuleSeeder::class,
            RoleTemplateSeeder::class,
        ]);

        $this->seedPlatformAdmin();
    }

    protected function seedPlatformAdmin(): void
    {
        $email = env('PLATFORM_ADMIN_EMAIL');
        $name = env('PLATFORM_ADMIN_NAME', 'Platform Administrator');
        $password = env('PLATFORM_ADMIN_PASSWORD');

        if (! $email) {
            $this->command?->info('PLATFORM_ADMIN_EMAIL tidak diset — admin platform bisa dibuat manual setelah deploy.');

            return;
        }

        DemoGuard::assertNotDemoCredential($email, (string) $password);

        PlatformAdmin::updateOrCreate(
            ['email' => $email],
            [
                'full_name' => $name,
                'password' => Hash::make($password),
                'is_active' => true,
            ]
        );
    }
}
