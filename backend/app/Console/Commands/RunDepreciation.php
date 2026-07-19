<?php

namespace App\Console\Commands;

use App\Models\PdamOrganization;
use App\Services\AssetService;
use App\Support\TenantContext;
use Illuminate\Console\Command;

/**
 * RunDepreciation — cron penyusutan aset bulanan (Fase 7.2).
 * Dijadwalkan 02:00 tanggal 1: hitung penyusutan seluruh aset aktif tiap tenant
 * untuk periode berjalan (default: bulan lalu). Idempoten — aman diulang
 * (unique per aset+periode di tabel depreciation_entries).
 *
 * Contoh: php artisan pdam:run-depreciation --period=2026-07
 */
class RunDepreciation extends Command
{
    protected $signature = 'pdam:run-depreciation {--period= : Periode YYYY-MM (default: bulan lalu)}';

    protected $description = 'Hitung penyusutan bulanan aset tetap + auto-jurnal (lintas tenant)';

    public function handle(AssetService $assets): int
    {
        $period = $this->option('period') ?: now()->subMonth()->format('Y-m');
        $this->info("Menjalankan penyusutan aset periode {$period}...");

        $orgs = PdamOrganization::query()->pluck('id');
        $grandCount = 0;
        $grandTotal = 0.0;

        foreach ($orgs as $orgId) {
            TenantContext::set($orgId);

            $result = $assets->runDepreciationForPeriod($period);
            $grandCount += $result['count'];
            $grandTotal += $result['total'];

            if ($result['count'] > 0) {
                $this->line("  Tenant {$orgId}: {$result['count']} aset, total Rp".number_format($result['total'], 2));
            }

            TenantContext::clear();
        }

        $this->info("Selesai. {$grandCount} aset disusutkan, total Rp".number_format($grandTotal, 2).' lintas tenant.');

        return self::SUCCESS;
    }
}
