<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Module;
use App\Models\PdamOrganization;
use App\Models\Permission;
use App\Models\PlatformAdmin;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\DemoTenantSeeder;
use Database\Seeders\ProductionKernelSeeder;
use Database\Seeders\Support\DemoGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class ProductionSeederIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setEnvironment(string $env): void
    {
        $this->app->instance('env', $env);
    }

    protected function seedForced(string $class): void
    {
        $this->artisan('db:seed', ['--class' => $class, '--force' => true])->run();
    }

    public function test_database_seeder_skips_all_demo_data_in_production(): void
    {
        $this->setEnvironment('production');

        $this->seedForced(DatabaseSeeder::class);

        $this->assertGreaterThan(0, Module::count(), 'katalog modul harus tetap terisi (kernel production)');
        $this->assertGreaterThan(0, Permission::count());
        $this->assertSame(0, PlatformAdmin::count(), 'akun superadmin demo tidak boleh dibuat di production');
        $this->assertSame(0, PdamOrganization::count(), 'tenant demo tidak boleh dibuat di production');
        $this->assertSame(0, Customer::count(), 'customer demo tidak boleh dibuat di production');
    }

    public function test_database_seeder_includes_demo_on_non_production(): void
    {
        $this->setEnvironment('testing');

        $this->assertTrue(DemoGuard::isAllowed());

        $this->seedForced(ProductionKernelSeeder::class);
        $this->seedForced(DemoTenantSeeder::class);

        $this->assertGreaterThanOrEqual(2, PdamOrganization::count());
    }

    public function test_demo_seeder_is_directly_blocked_in_production(): void
    {
        $this->setEnvironment('production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DemoSeeder');

        $this->seedForced(DemoSeeder::class);
    }

    public function test_production_block_is_absolute_even_with_allow_demo_seed_env(): void
    {
        $this->setEnvironment('production');

        $_ENV['PDAM_ALLOW_DEMO_SEED'] = 'true';
        $_SERVER['PDAM_ALLOW_DEMO_SEED'] = 'true';
        putenv('PDAM_ALLOW_DEMO_SEED=true');

        try {
            $this->assertFalse(DemoGuard::isAllowed(), 'escape-hatch env tidak boleh membuka demo di production');

            $this->expectException(RuntimeException::class);
            DemoGuard::assertNonProduction('DemoTenantSeeder');
        } finally {
            unset($_ENV['PDAM_ALLOW_DEMO_SEED'], $_SERVER['PDAM_ALLOW_DEMO_SEED']);
            putenv('PDAM_ALLOW_DEMO_SEED');
        }
    }

    public function test_demo_guard_rejects_demo_credential_in_production(): void
    {
        $this->expectException(RuntimeException::class);
        DemoGuard::assertNotDemoCredential('superadmin@gmail.com', 'Kuat-Absah-9123!');
    }

    public function test_demo_guard_accepts_strong_non_demo_credential(): void
    {
        DemoGuard::assertNotDemoCredential('ops@pdam-jabar.go.id', 'Kuat-Absah-9123!');
        $this->assertTrue(Hash::check('Kuat-Absah-9123!', Hash::make('Kuat-Absah-9123!')));
    }

    public function test_production_kernel_rejects_demo_password_via_env(): void
    {
        $this->setEnvironment('production');

        $_ENV['PLATFORM_ADMIN_EMAIL'] = 'admin@example.org';
        $_SERVER['PLATFORM_ADMIN_EMAIL'] = 'admin@example.org';
        $_ENV['PLATFORM_ADMIN_PASSWORD'] = '12345678';
        $_SERVER['PLATFORM_ADMIN_PASSWORD'] = '12345678';

        try {
            $this->expectException(RuntimeException::class);
            $this->seedForced(ProductionKernelSeeder::class);
        } finally {
            unset($_ENV['PLATFORM_ADMIN_EMAIL'], $_SERVER['PLATFORM_ADMIN_EMAIL']);
            unset($_ENV['PLATFORM_ADMIN_PASSWORD'], $_SERVER['PLATFORM_ADMIN_PASSWORD']);
        }
    }

    public function test_production_kernel_creates_env_admin_when_credentials_valid(): void
    {
        $this->setEnvironment('production');

        $_ENV['PLATFORM_ADMIN_EMAIL'] = 'platform.admin@pdam-jabar.go.id';
        $_SERVER['PLATFORM_ADMIN_EMAIL'] = $_ENV['PLATFORM_ADMIN_EMAIL'];
        $_ENV['PLATFORM_ADMIN_PASSWORD'] = 'Kuat-Absah-9123!';
        $_SERVER['PLATFORM_ADMIN_PASSWORD'] = $_ENV['PLATFORM_ADMIN_PASSWORD'];

        try {
            $this->seedForced(ProductionKernelSeeder::class);

            $this->assertDatabaseHas('platform_admins', [
                'email' => 'platform.admin@pdam-jabar.go.id',
            ]);
        } finally {
            unset($_ENV['PLATFORM_ADMIN_EMAIL'], $_SERVER['PLATFORM_ADMIN_EMAIL']);
            unset($_ENV['PLATFORM_ADMIN_PASSWORD'], $_SERVER['PLATFORM_ADMIN_PASSWORD']);
        }
    }
}
