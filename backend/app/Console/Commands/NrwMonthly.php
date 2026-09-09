<?php

namespace App\Console\Commands;

use App\Models\DmaZone;
use App\Models\PdamOrganization;
use App\Services\Geo\NrwAnalysisService;
use App\Support\TenantContext;
use Illuminate\Console\Command;

/**
 * NrwMonthly — cron rutin bulanan (tgl 1, 04:00): hitung & SIMPAN balance NRW
 * IWA semua DMA aktif untuk periode bulan tertutup. nrw_balances jadi tren
 * nyata di panel GIS (endpoint nrw-trend.json) tanpa klik manual.
 *
 * Pola lintas-tenant sama seperti pdam:depreciation: one org at a time,
 * TenantContext::set() → scoped model queries valid.
 */
class NrwMonthly extends Command
{
    protected $signature = 'pdam:nrw-monthly
                            {period? : Periode Y-m (default: bulan lalu)}
                            {--dma= : Batasi satu DMA (id)}';

    protected $description = 'Hitung + simpan NRW bulanan SEMUA DMA aktif lintas tenant (cron tgl 1)';

    public function __construct(private NrwAnalysisService $nrw)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $period = $this->argument('period') ?: now()->startOfMonth()->subMonthNoOverflow()->format('Y-m');
        if (! preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
            $this->error('Format period harus Y-m (mis. 2026-08).');

            return self::FAILURE;
        }

        $dmaFilter = $this->option('dma');
        $total = ['ok' => 0, 'skip' => 0, 'fail' => 0];

        foreach (PdamOrganization::query()->pluck('id') as $orgId) {
            TenantContext::set((int) $orgId);
            try {
                $dmas = DmaZone::where('is_active', true)
                    ->when($dmaFilter, fn ($q) => $q->where('id', (int) $dmaFilter))
                    ->get();

                foreach ($dmas as $dma) {
                    try {
                        $res = $this->nrw->calculate($dma, (string) $period);
                        if (! empty($res['ok'])) {
                            $total['ok']++;
                            $this->line(sprintf(
                                '  [%s] %s: NRW %s%% (suplai %s m³ vs tercatat %s m³)',
                                $orgId, $dma->code, $res['nrw_percent'],
                                number_format((float) $res['supply_m3'], 0, ',', '.'),
                                number_format((float) $res['billed_m3'], 0, ',', '.')
                            ));
                        } else {
                            $total['skip']++;
                            $this->warn('  ['.$orgId.'] '.$dma->code.': '.($res['error'] ?? 'tidak bisa dihitung'));
                        }
                    } catch (\Throwable $e) {
                        $total['fail']++;
                        $this->warn('  ['.$orgId.'] '.$dma->code.': gagal — '.$e->getMessage());
                    }
                }

                if ($dmas->isEmpty()) {
                    $this->comment("  [org {$orgId}] tidak ada DMA aktif — lewat.");
                }
            } finally {
                TenantContext::clear();
            }
        }

        $this->info("Periode {$period}: terhitung={$total['ok']} tanpa-data={$total['skip']} error={$total['fail']} (semua tenant).");

        return self::SUCCESS;
    }
}
