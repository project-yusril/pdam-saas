<?php

namespace Database\Seeders;

use Database\Seeders\Support\DemoGuard;
use Illuminate\Database\Seeder;

/**
 * DatabaseSeeder — router seed (Fase 0).
 *
 * Production  : HANYA ProductionKernelSeeder (katalog platform; tanpa demo).
 * Non-prod    : ProductionKernelSeeder + DemoSeeder (3 tenant demo terintegrasi).
 *
 * Data tenant produksi dibuat via TenantProvisioningService / marketplace,
 * bukan lewat seeder. Detail fixture: SEED_DATA.md.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ProductionKernelSeeder::class);

        if (DemoGuard::isAllowed()) {
            $this->call(DemoSeeder::class);
        } else {
            $this->command?->warn('Environment production — DemoSeeder DILEWATI (tidak ada akun/fixture demo yang dibuat).');
        }
    }
}
