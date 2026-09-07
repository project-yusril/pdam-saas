<?php

namespace Database\Seeders;

use App\Models\PdamOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * MeterExtendedSeeder — modul METX (Meter Analytics).
 *
 * Mengisi:
 *  - meter_lifecycle_events : jejak lifecycle tiap meter terpasang
 *  - meter_stock            : meter cadangan di gudang (belum terpasang)
 *  - meter_anomalies        : hasil deteksi anomali konsumsi
 *  - meter_replacements     : contoh penggantian meter rusak
 *
 * METX aktif penuh di Canada; Brazil tidak punya METX (locked) → hanya Canada diisi.
 * Idempotent: dilewati bila meter_lifecycle_events sudah ada.
 */
class MeterExtendedSeeder extends Seeder
{
    /** Tenant yang punya modul METX aktif. */
    private const METX_TENANTS = ['pdam-canada'];

    public function run(): void
    {
        if (DB::table('meter_lifecycle_events')->exists()) {
            return;
        }

        foreach (self::METX_TENANTS as $code) {
            $org = PdamOrganization::where('code', $code)->first();
            if (! $org) {
                continue;
            }
            $this->seedForTenant($org->id);
        }
    }

    private function seedForTenant(int $orgId): void
    {
        $meters = DB::table('meters')->where('pdam_org_id', $orgId)->get();

        // Lifecycle event "installed" untuk tiap meter terpasang
        foreach ($meters as $meter) {
            DB::table('meter_lifecycle_events')->insert([
                'pdam_org_id' => $orgId,
                'meter_id' => $meter->id,
                'event' => 'installed',
                'from_status' => 'gudang',
                'to_status' => 'terpasang',
                'customer_id' => $meter->customer_id,
                'note' => 'Meter terpasang saat aktivasi pelanggan.',
                'performed_by' => null,
                'created_at' => $meter->install_date ?? now()->subMonths(6),
                'updated_at' => now(),
            ]);
        }

        // Meter cadangan di gudang utama (belum terpasang)
        $warehouse = DB::table('warehouses')->where('pdam_org_id', $orgId)->where('warehouse_type', 'main')->first()
            ?? DB::table('warehouses')->where('pdam_org_id', $orgId)->first();
        $meterMaterial = DB::table('materials')->where('pdam_org_id', $orgId)->where('category', 'meter')->first();

        for ($i = 1; $i <= 5; $i++) {
            DB::table('meter_stock')->insert([
                'pdam_org_id' => $orgId,
                'warehouse_id' => $warehouse?->id,
                'material_id' => $meterMaterial?->id,
                'meter_id' => null,
                'brand' => 'Amico',
                'diameter' => '0.5',
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Anomali konsumsi pada 2 pelanggan (spike & zero_streak)
        $customers = DB::table('customers')->where('pdam_org_id', $orgId)->limit(2)->get();
        $rules = [
            ['spike', 'high', 20, 85, 'Lonjakan pemakaian tak wajar 4x lipat'],
            ['zero_streak', 'medium', 15, 0, 'Pemakaian nol 2 periode berturut, indikasi meter macet'],
        ];
        foreach ($customers as $i => $customer) {
            [$rule, $severity, $expected, $actual, $desc] = $rules[$i % count($rules)];
            $meter = $meters->firstWhere('customer_id', $customer->id);
            DB::table('meter_anomalies')->insert([
                'pdam_org_id' => $orgId,
                'customer_id' => $customer->id,
                'meter_id' => $meter?->id,
                'period' => '2026-06',
                'rule_code' => $rule,
                'severity' => $severity,
                'expected_value' => $expected,
                'actual_value' => $actual,
                'description' => $desc,
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Penggantian meter (1 contoh)
        $target = $customers->first();
        if ($target) {
            DB::table('meter_replacements')->insert([
                'pdam_org_id' => $orgId,
                'customer_id' => $target->id,
                'old_serial' => $target->meter_serial_number,
                'new_serial' => 'MTR-NEW-'.$target->id,
                'old_final_reading' => 120,
                'new_initial_reading' => 0,
                'replaced_at' => now()->subDays(7)->toDateString(),
                'reason' => 'Meter macet, hasil deteksi anomali',
                'processed_by' => null,
                'created_at' => now()->subDays(7),
                'updated_at' => now()->subDays(7),
            ]);
        }
    }
}
