<?php

namespace Database\Seeders\Support;

use RuntimeException;

/**
 * DemoGuard — pemisah tegas jalur seed demo vs production (temuan2.md P1).
 *
 * Semua seeder yang membuat tenant/user/fixture demo WAJIB memanggil
 * assertNonProduction() agar password demo 12345678 dan fixture fiktif
 * tidak mungkin dibuat pada deployment production.
 */
final class DemoGuard
{
    public const DEMO_PASSWORD = '12345678';

    /**
     * Production: DIBLOKIR MUTLAK — tidak ada env/PDAM_ALLOW_DEMO_SEED yang bisa
     * membuka jalur demo pada APP_ENV=production (docs claim "hard-blocked" jujur).
     * Non-production: boleh; staging yang butuh demo harus menjalankan dengan
     * APP_ENV staging/local secara eksplisit dan sadar.
     */
    public static function isAllowed(): bool
    {
        if (app()->environment('production')) {
            return false;
        }

        return app()->environment('local', 'testing', 'development')
            || filter_var(env('PDAM_ALLOW_DEMO_SEED', false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function assertNonProduction(string $what): void
    {
        if (! self::isAllowed()) {
            throw new RuntimeException(
                "Seed demo '{$what}' diblokir mutlak pada APP_ENV=production (DemoGuard tidak punya escape-hatch env). "
                .'Provisioning production: ProductionKernelSeeder + TenantProvisioningService/marketplace.'
            );
        }
    }

    public static function assertNotDemoCredential(string $email, string $password): void
    {
        $demoEmails = ['superadmin@gmail.com', 'admin_tenant@gmail.com'];
        if (in_array($email, $demoEmails, true) || $password === self::DEMO_PASSWORD) {
            throw new RuntimeException('Kredensial demo tidak boleh digunakan di production.');
        }

        if (strlen($password) < 12) {
            throw new RuntimeException('Password admin platform production minimal 12 karakter.');
        }
    }
}
