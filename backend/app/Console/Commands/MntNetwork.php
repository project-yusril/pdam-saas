<?php

namespace App\Console\Commands;

use App\Models\PdamOrganization;
use App\Services\Geo\PreventiveMaintenanceService;
use App\Support\TenantContext;
use Illuminate\Console\Command;

/**
 * MntNetwork — cron harian jaringan: semua jadwal preventif valve/hydrant
 * (asset_type = gis_feature) yang jatuh tempo → WorkOrder inspeksi + geser
 * due-date maju satu siklus. Integrasi GIS↔MNT satu-tombol otomatis.
 */
class MntNetwork extends Command
{
    protected $signature = 'pdam:mnt-network {--org= : Batasi satu org id}';

    protected $description = 'Jadwalkan preventif jaringan → buat WO inspeksi dari schedule due';

    public function __construct(private PreventiveMaintenanceService $preventive)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $orgIds = $this->option('org')
            ? collect([(int) $this->option('org')])
            : PdamOrganization::query()->pluck('id');

        $total = 0;
        foreach ($orgIds as $orgId) {
            TenantContext::set((int) $orgId);
            try {
                $n = $this->preventive->processDueDates((int) $orgId);
                $total += $n;
                if ($n > 0) {
                    $this->info("  org {$orgId}: {$n} WO preventif dibuat.");
                }
            } finally {
                TenantContext::clear();
            }
        }

        $this->info("Cron jaringan: {$total} WO preventif terbit dari due-dates (semua org).");

        return self::SUCCESS;
    }
}
