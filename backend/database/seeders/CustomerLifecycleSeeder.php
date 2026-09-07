<?php

namespace Database\Seeders;

use App\Models\PdamOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * CustomerLifecycleSeeder — modul Survey (SRV) & lifecycle pelanggan.
 *
 * Mengisi:
 *  - customer_prospects   : calon pelanggan (berbagai tahap status)
 *  - survey_reports       : laporan survey lapangan
 *  - installation_schedules : jadwal pemasangan
 *  - customer_status_history : jejak perubahan status pelanggan existing
 *  - disconnections / reconnections : contoh isolir & buka isolir
 *  - ownership_transfers  : contoh balik nama
 *
 * Idempotent: dilewati bila customer_prospects sudah ada.
 */
class CustomerLifecycleSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('customer_prospects')->exists()) {
            return;
        }

        foreach (['pdam-canada', 'pdam-brazil'] as $code) {
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
        $tariff = DB::table('tariff_categories')->where('pdam_org_id', $orgId)->first();

        // ── Prospek + survey + jadwal pemasangan ──
        $prospects = [
            ['Andi Pratama', 'survey_approved', 'feasible', 'approved'],
            ['Maria Ulfa', 'surveying', null, null],
            ['Joko Widodo', 'installation_scheduled', 'feasible', 'approved'],
            ['Dedi Setiawan', 'installed', 'feasible', 'approved'],
        ];

        foreach ($prospects as $i => [$name, $status, $recommendation, $reviewStatus]) {
            $prospectId = DB::table('customer_prospects')->insertGetId([
                'pdam_org_id' => $orgId,
                'registration_number' => sprintf('REG-%d-%04d', $orgId, $i + 1),
                'nik' => null,
                'full_name' => $name,
                'birth_place' => 'Indonesia',
                'birth_date' => '1990-01-0'.($i + 1),
                'address' => 'Alamat KTP '.$name,
                'installation_address' => 'Alamat pemasangan '.$name,
                'zone_id' => $zone?->id,
                'phone' => '08123456700'.$i,
                'tariff_category_id' => $tariff?->id,
                'status' => $status,
                'installation_fee' => 1_500_000,
                'created_at' => now()->subDays(10 - $i),
                'updated_at' => now()->subDays(5 - $i),
            ]);

            // Survey report bila sudah disurvei
            if ($recommendation !== null) {
                DB::table('survey_reports')->insert([
                    'pdam_org_id' => $orgId,
                    'prospect_id' => $prospectId,
                    'surveyor_id' => 0,
                    'distance_to_main_pipe' => 12.5 + $i,
                    'building_condition' => 'permanen',
                    'accessibility' => 'mudah',
                    'estimated_cost' => 1_500_000,
                    'recommendation' => $recommendation,
                    'review_status' => $reviewStatus,
                    'review_notes' => 'Layak dipasang.',
                    'reviewed_at' => now()->subDays(3),
                    'created_at' => now()->subDays(4),
                    'updated_at' => now()->subDays(3),
                ]);
            }

            // Jadwal pemasangan bila statusnya installation_scheduled
            if ($status === 'installation_scheduled') {
                DB::table('installation_schedules')->insert([
                    'pdam_org_id' => $orgId,
                    'prospect_id' => $prospectId,
                    'scheduled_date' => now()->addDays(3)->toDateString(),
                    'status' => 'scheduled',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // ── Lifecycle pelanggan existing ──
        $customers = DB::table('customers')->where('pdam_org_id', $orgId)->limit(2)->get();
        foreach ($customers as $idx => $customer) {
            // Status history: active → isolir → active (reconnection)
            DB::table('customer_status_history')->insert([
                'pdam_org_id' => $orgId,
                'customer_id' => $customer->id,
                'from_status' => 'active',
                'to_status' => 'isolir',
                'reason' => 'Tunggakan lebih dari 3 bulan',
                'changed_by' => null,
                'created_at' => now()->subDays(20),
            ]);

            $disconnectionId = DB::table('disconnections')->insertGetId([
                'pdam_org_id' => $orgId,
                'customer_id' => $customer->id,
                'type' => 'isolir',
                'reason' => 'Tunggakan lebih dari 3 bulan',
                'effective_date' => now()->subDays(20)->toDateString(),
                'requested_by' => null,
                'created_at' => now()->subDays(20),
                'updated_at' => now()->subDays(20),
            ]);

            // Pelanggan pertama disambung lagi
            if ($idx === 0) {
                DB::table('reconnections')->insert([
                    'pdam_org_id' => $orgId,
                    'customer_id' => $customer->id,
                    'disconnection_id' => $disconnectionId,
                    'fee' => 150_000,
                    'effective_date' => now()->subDays(10)->toDateString(),
                    'processed_by' => null,
                    'created_at' => now()->subDays(10),
                    'updated_at' => now()->subDays(10),
                ]);

                DB::table('customer_status_history')->insert([
                    'pdam_org_id' => $orgId,
                    'customer_id' => $customer->id,
                    'from_status' => 'isolir',
                    'to_status' => 'active',
                    'reason' => 'Pembayaran tunggakan + biaya buka isolir lunas',
                    'changed_by' => null,
                    'created_at' => now()->subDays(10),
                ]);
            }
        }

        // ── Balik nama (satu contoh) ──
        $lastCustomer = DB::table('customers')->where('pdam_org_id', $orgId)->orderByDesc('id')->first();
        if ($lastCustomer) {
            DB::table('ownership_transfers')->insert([
                'pdam_org_id' => $orgId,
                'customer_id' => $lastCustomer->id,
                'old_owner_name' => $lastCustomer->full_name,
                'new_owner_name' => 'Pemilik Baru Sdr. Rahmat',
                'new_owner_phone' => '081299998888',
                'effective_date' => now()->subDays(5)->toDateString(),
                'processed_by' => null,
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ]);
        }
    }
}
