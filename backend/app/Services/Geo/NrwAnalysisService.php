<?php

namespace App\Services\Geo;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\DistributionReading;
use App\Models\DmaZone;
use App\Models\GisFeature;
use App\Models\NrwBalance;

/**
 * NrwAnalysisService — hitung NRW IWA per DMA dari data transaksi riil:
 *  - System Input  = rata-rata flow_rate_m3h reading distribusi × jam periode.
 *  - Billed Metered = Σ consumption tagihan periode tsb untuk pelanggan
 *    yang koordinatnya di dalam polygon DMA (fallback: zone_id / semua aktif).
 *  + total pipa dalam DMA untuk metrik ILI (lost connections per day per km pipa).
 */
class NrwAnalysisService
{
    public function __construct(private NetworkGraphService $graph) {}

    /** @return array{ok:bool, balance?:NrwBalance, supply_m3?:float, billed_m3?:float, error?:string} */
    public function calculate(DmaZone $dma, string $period): array
    {
        [$year, $month] = array_map('intval', explode('-', $period));
        $start = mktime(0, 0, 0, $month, 1, $year);
        $days = (int) date('t', $start);
        $endDay = date('Y-m-t', $start);

        $avgFlow = (float) DistributionReading::query()
            ->where('dma_zone_id', $dma->id)
            ->whereBetween('reading_at', [date('Y-m-d 00:00:00', $start), $endDay.' 23:59:59'])
            ->avg('flow_rate_m3h');

        if ($avgFlow <= 0) {
            return ['ok' => false, 'error' => 'Belum ada data DistributionReading untuk DMA/periode ini.'];
        }

        $systemInput = $avgFlow * 24 * $days;
        $billedMetered = (float) Bill::query()
            ->where('period', $period)
            ->whereIn('customer_id', $this->customerIdsInDma($dma))
            ->sum('consumption');

        $waterLosses = max(0, $systemInput - $billedMetered);
        $nrwPercent = $systemInput > 0 ? round($waterLosses / $systemInput * 100, 2) : 0.0;

        // ILI ≈ m³ kehilangan / koneksi / hari ÷ (panjang pipa km / koneksi)
        $connections = Customer::query()
            ->whereIn('id', $this->customerIdsInDma($dma))
            ->where('status', 'active')->count() ?: $dma->total_connections ?: 1;
        $pipeKm = $this->pipeLengthKm();
        $ili = $pipeKm > 0
            ? round($waterLosses / max($connections, 1) / $days, 2)
            : null;

        $balance = NrwBalance::updateOrCreate(
            ['pdam_org_id' => $dma->pdam_org_id, 'dma_zone_id' => $dma->id, 'period' => $period],
            [
                'system_input_m3' => $systemInput,
                'billed_metered_m3' => $billedMetered,
                'unbilled_metered_m3' => 0,
                'unbilled_unmetered_m3' => 0,
                'authorised_consumption_m3' => $billedMetered,
                'water_losses_m3' => $waterLosses,
                'apparent_losses_m3' => 0,
                'real_losses_m3' => $waterLosses,
                'nrw_percentage' => $nrwPercent,
                'ili' => $ili,
            ]
        );

        return [
            'ok' => true,
            'balance' => $balance,
            'supply_m3' => round($systemInput, 2),
            'billed_m3' => round($billedMetered, 2),
            'nrw_percent' => $nrwPercent,
            'connections' => $connections,
        ];
    }

    /**
     * Ringkasan dashboard per DMA: balance terpilih/terkini + status.
     * Bila periode diminta tapi belum ada balance → hitung langsung (lazy,
     * sekali lalu tersimpan di nrw_balances).
     */
    public function summary(?string $period = null): array
    {
        $dmas = DmaZone::where('is_active', true)->get();

        return $dmas->map(function (DmaZone $dma) use ($period) {
            $q = NrwBalance::where('dma_zone_id', $dma->id);
            if ($period) {
                $q->where('period', $period);
            }
            $b = $q->latest('period')->first();

            if (! $b && $period) {
                try {
                    if (! $b) {
                        $r = $this->calculate($dma, $period);
                        $b = ($r['ok'] ?? false) ? ($r['balance'] ?? null) : null;
                    }
                } catch (\Throwable $e) {
                    $b = null;
                }
            }

            return [
                'dma_id' => $dma->id,
                'code' => $dma->code,
                'name' => $dma->name,
                'has_boundary' => ! empty($dma->boundary),
                'period' => $b?->period,
                'system_input_m3' => $b ? (float) $b->system_input_m3 : null,
                'billed_metered_m3' => $b ? (float) $b->billed_metered_m3 : null,
                'water_losses_m3' => $b ? (float) $b->water_losses_m3 : null,
                'nrw_percentage' => $b ? (float) $b->nrw_percentage : null,
                'ili' => $b?->ili !== null ? (float) $b->ili : null,
                'status' => $b ? match (true) {
                    (float) $b->nrw_percentage > 30 => 'kritis',
                    (float) $b->nrw_percentage > 20 => 'waspada',
                    default => 'baik',
                } : 'belum_dihitung',
            ];
        })->values()->all();
    }

    // ── internal ───────────────────────────────────────────────────────────

    /** @return array<int,int> */
    private function customerIdsInDma(DmaZone $dma): array
    {
        if (! empty($dma->boundary)) {
            $polygon = $this->normalizeRing($dma->boundary);
            if (count($polygon) >= 3) {
                return Customer::query()
                    ->whereNotNull('latitude')->whereNotNull('longitude')
                    ->where('status', '!=', 'inactive')
                    ->cursor()
                    ->filter(fn (Customer $c) => $this->graph->pointInPolygon(
                        [(float) $c->longitude, (float) $c->latitude], $polygon
                    ))
                    ->pluck('id')->all();
            }
        }

        if ($dma->zone_id) {
            return Customer::query()
                ->where('status', '!=', 'inactive')
                ->where('zone_id', $dma->zone_id)
                ->pluck('id')->all();
        }

        return Customer::query()->where('status', '!=', 'inactive')->pluck('id')->all();
    }

    /** GeoJSON Polygon ring / array titik polos → [[lon,lat], ...] */
    private function normalizeRing(array $boundary): array
    {
        $type = $boundary['type'] ?? null;
        if ($type === 'Polygon') {
            return array_map(fn ($p) => [(float) $p[0], (float) $p[1]], $boundary['coordinates'][0] ?? []);
        }
        if (array_is_list($boundary)) {
            return array_map(fn ($p) => [(float) $p[0], (float) $p[1]], $boundary);
        }

        return [];
    }

    private function pipeLengthKm(): float
    {
        $sum = 0.0;
        GisFeature::query()->where('feature_type', 'pipe')->cursor()
            ->each(function (GisFeature $pipe) use (&$sum) {
                $props = $pipe->properties ?? [];
                $len = $props['length_meters'] ?? $this->graph->lineLengthMeters($pipe->geometry['coordinates'] ?? []);
                $sum += (float) $len;
            });

        return round($sum / 1000, 3);
    }
}
