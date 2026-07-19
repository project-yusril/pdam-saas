<?php

namespace Database\Seeders;

use App\Models\PdamOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * SmartUtilitySeeder — modul Tier-3 (IoT/SCADA, Produksi, DMA/NRW, ML). Hanya tenant Canada (full).
 *
 * Mengisi: sensor_readings, production_logs, dma_zones, distribution_readings,
 * nrw_balances, ml_predictions. Idempotent: dilewati bila dma_zones sudah ada.
 */
class SmartUtilitySeeder extends Seeder
{
    private const TIER3_TENANTS = ['pdam-canada'];

    public function run(): void
    {
        if (DB::table('dma_zones')->exists()) {
            return;
        }

        foreach (self::TIER3_TENANTS as $code) {
            $org = PdamOrganization::where('code', $code)->first();
            if (! $org) {
                continue;
            }
            $this->seedForTenant($org->id);
        }
    }

    private function seedForTenant(int $orgId): void
    {
        $zone = DB::table('zones')->where('pdam_org_id', $orgId)->first();
        $meter = DB::table('meters')->where('pdam_org_id', $orgId)->first();
        $customer = DB::table('customers')->where('pdam_org_id', $orgId)->first();

        // ── DMA Zone ──
        $dmaId = DB::table('dma_zones')->insertGetId([
            'pdam_org_id' => $orgId, 'zone_id' => $zone?->id, 'code' => 'DMA-01',
            'name' => 'DMA Zona Utara', 'total_connections' => 1200, 'base_demand_m3day' => 3600,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Sensor readings (AMI, 5 titik waktu) ──
        for ($h = 5; $h >= 1; $h--) {
            DB::table('sensor_readings')->insert([
                'pdam_org_id' => $orgId, 'meter_id' => $meter?->id, 'customer_id' => $customer?->id,
                'value' => 1500 + $h * 2, 'flow_rate' => 0.85, 'pressure' => 2.4,
                'battery' => 92.5, 'signal_strength' => 78.0,
                'reading_at' => now()->subHours($h), 'source' => 'lorawan',
                'device_id' => 'AMI-DEV-001', 'validated' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // ── Production logs (3 hari) ──
        foreach ([['2026-06-05', 15000, 14200, 13800], ['2026-06-06', 15200, 14400, 14000], ['2026-06-07', 14800, 14000, 13600]] as [$date, $raw, $treated, $dist]) {
            DB::table('production_logs')->insert([
                'pdam_org_id' => $orgId, 'production_date' => $date,
                'raw_water_m3' => $raw, 'treated_water_m3' => $treated, 'distributed_water_m3' => $dist,
                'pump_runtime_hours' => 22.5, 'power_consumption_kwh' => 4800,
                'turbidity_ntu' => 1.2, 'ph' => 7.1, 'chlorine_residual' => 0.6, 'status' => 'normal',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // ── Distribution readings (SCADA, 3 titik) ──
        for ($h = 3; $h >= 1; $h--) {
            DB::table('distribution_readings')->insert([
                'pdam_org_id' => $orgId, 'dma_zone_id' => $dmaId, 'reading_at' => now()->subHours($h),
                'flow_rate_m3h' => 150.5, 'pressure_bar' => 2.5, 'reservoir_level_percent' => 78.0,
                'chlorine_residual' => 0.55, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // ── NRW balance (water balance IWA) ──
        $systemInput = 108000;   // m3 sebulan
        $billed = 82000;
        $unbilledMetered = 2000;
        $unbilledUnmetered = 1500;
        $authorised = $billed + $unbilledMetered + $unbilledUnmetered;
        $losses = $systemInput - $authorised;
        $apparent = 6000;
        $real = $losses - $apparent;
        DB::table('nrw_balances')->insert([
            'pdam_org_id' => $orgId, 'dma_zone_id' => $dmaId, 'period' => '2026-06',
            'system_input_m3' => $systemInput, 'billed_metered_m3' => $billed,
            'unbilled_metered_m3' => $unbilledMetered, 'unbilled_unmetered_m3' => $unbilledUnmetered,
            'authorised_consumption_m3' => $authorised, 'water_losses_m3' => $losses,
            'apparent_losses_m3' => $apparent, 'real_losses_m3' => $real,
            'nrw_percentage' => round($losses / $systemInput * 100, 2), 'ili' => 2.1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── ML predictions ──
        DB::table('ml_predictions')->insert([
            [
                'pdam_org_id' => $orgId, 'model_name' => 'demand_forecast_v1', 'prediction_type' => 'demand',
                'entity_type' => 'dma_zone', 'entity_id' => $dmaId, 'period' => '2026-07',
                'predicted_value' => 110500, 'confidence_min' => 105000, 'confidence_max' => 116000,
                'features' => json_encode(['seasonality' => 'dry', 'trend' => 'up']),
                'status' => 'predicted', 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'pdam_org_id' => $orgId, 'model_name' => 'leak_detection_v1', 'prediction_type' => 'anomaly',
                'entity_type' => 'dma_zone', 'entity_id' => $dmaId, 'period' => '2026-06',
                'predicted_value' => 1, 'confidence_min' => 0.72, 'confidence_max' => 0.95,
                'features' => json_encode(['night_flow_high' => true]),
                'status' => 'predicted', 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }
}
