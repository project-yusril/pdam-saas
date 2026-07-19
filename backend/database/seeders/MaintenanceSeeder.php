<?php

namespace Database\Seeders;

use App\Models\PdamOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * MaintenanceSeeder — modul MNT (Pemeliharaan Preventif). Hanya tenant Canada (full).
 *
 * Mengisi: maintenance_schedules, maintenance_records.
 * Non-posting GL. Idempotent: dilewati bila maintenance_schedules sudah ada.
 */
class MaintenanceSeeder extends Seeder
{
    private const MNT_TENANTS = ['pdam-canada'];

    public function run(): void
    {
        if (DB::table('maintenance_schedules')->exists()) {
            return;
        }

        foreach (self::MNT_TENANTS as $code) {
            $org = PdamOrganization::where('code', $code)->first();
            if (! $org) {
                continue;
            }
            $this->seedForTenant($org->id);
        }
    }

    private function seedForTenant(int $orgId): void
    {
        $technician = DB::table('users')->where('pdam_org_id', $orgId)->first();
        $asset = DB::table('fixed_assets')->where('pdam_org_id', $orgId)->where('code', 'AST-003')->first();

        $schedules = [
            ['MNT-001', 'Servis Rutin Pompa Distribusi', 'pump', 'monthly', 1],
            ['MNT-002', 'Kalibrasi Meter Induk', 'meter', 'quarterly', 3],
            ['MNT-003', 'Pembersihan Bak Filtrasi IPA', 'ipa', 'weekly', 1],
        ];

        foreach ($schedules as $i => [$code, $name, $assetType, $freq, $interval]) {
            $scheduleId = DB::table('maintenance_schedules')->insertGetId([
                'pdam_org_id' => $orgId, 'code' => $code, 'name' => $name,
                'asset_type' => $assetType, 'asset_id' => $i === 0 ? $asset?->id : null,
                'frequency' => $freq, 'interval_value' => $interval,
                'next_due_date' => now()->addDays(7 * ($i + 1))->toDateString(),
                'last_completed_date' => now()->subDays(7 * ($i + 1))->toDateString(),
                'is_active' => true,
                'checklist_json' => json_encode(['Cek tekanan', 'Cek kebocoran', 'Ganti pelumas']),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            // Record eksekusi terakhir
            DB::table('maintenance_records')->insert([
                'pdam_org_id' => $orgId, 'schedule_id' => $scheduleId,
                'execution_date' => now()->subDays(7 * ($i + 1))->toDateString(),
                'technician_id' => $technician?->id,
                'findings' => 'Kondisi normal, dilakukan perawatan rutin.',
                'cost_labor' => 250_000, 'cost_material' => 150_000,
                'outcome' => 'completed', 'recommendations' => 'Lanjut jadwal berikutnya.',
                'journal_entry_id' => null,
                'created_at' => now()->subDays(7 * ($i + 1)), 'updated_at' => now()->subDays(7 * ($i + 1)),
            ]);
        }
    }
}
