<?php

namespace Database\Seeders;

use App\Models\PdamOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * FieldServiceSeeder — modul FSM (Field Service Management). Hanya tenant Canada (full).
 *
 * Mengisi: work_orders, work_order_logs, technician_locations.
 * Semua non-posting GL. Idempotent: dilewati bila work_orders sudah ada.
 */
class FieldServiceSeeder extends Seeder
{
    private const FSM_TENANTS = ['pdam-canada'];

    public function run(): void
    {
        if (DB::table('work_orders')->exists()) {
            return;
        }

        foreach (self::FSM_TENANTS as $code) {
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
        $customers = DB::table('customers')->where('pdam_org_id', $orgId)->limit(3)->get();
        $zone = DB::table('zones')->where('pdam_org_id', $orgId)->first();

        $woDefs = [
            ['leak_repair', 'high', 'completed', 'Perbaikan kebocoran pipa dinas'],
            ['new_installation', 'medium', 'in_progress', 'Pemasangan sambungan baru'],
            ['meter_check', 'low', 'open', 'Pengecekan meter bermasalah'],
        ];

        foreach ($woDefs as $i => [$type, $priority, $status, $desc]) {
            $customer = $customers[$i] ?? $customers->first();
            $woId = DB::table('work_orders')->insertGetId([
                'pdam_org_id' => $orgId, 'wo_number' => sprintf('WO-%d-%04d', $orgId, $i + 1),
                'type' => $type, 'priority' => $priority, 'customer_id' => $customer?->id,
                'zone_id' => $zone?->id, 'status' => $status,
                'sla_due_at' => now()->addDays(2),
                'assigned_to' => $status !== 'open' ? $technician?->id : null,
                'address' => 'Lokasi pekerjaan ' . ($i + 1), 'description' => $desc,
                'started_at' => $status !== 'open' ? now()->subDays(2) : null,
                'completed_at' => $status === 'completed' ? now()->subDay() : null,
                'resolution' => $status === 'completed' ? 'Pekerjaan selesai, pelanggan puas.' : null,
                'customer_signature' => $status === 'completed',
                'created_at' => now()->subDays(3), 'updated_at' => now(),
            ]);

            // Log status
            DB::table('work_order_logs')->insert([
                'pdam_org_id' => $orgId, 'work_order_id' => $woId,
                'from_status' => null, 'to_status' => 'open', 'action' => 'created',
                'note' => 'Work order dibuat.', 'user_id' => $technician?->id,
                'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3),
            ]);
            if ($status !== 'open') {
                DB::table('work_order_logs')->insert([
                    'pdam_org_id' => $orgId, 'work_order_id' => $woId,
                    'from_status' => 'open', 'to_status' => 'in_progress', 'action' => 'started',
                    'note' => 'Teknisi mulai bekerja.', 'user_id' => $technician?->id,
                    'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
                ]);
            }
            if ($status === 'completed') {
                DB::table('work_order_logs')->insert([
                    'pdam_org_id' => $orgId, 'work_order_id' => $woId,
                    'from_status' => 'in_progress', 'to_status' => 'completed', 'action' => 'completed',
                    'note' => 'Pekerjaan selesai.', 'user_id' => $technician?->id,
                    'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
                ]);
            }
        }

        // Lokasi teknisi terkini
        if ($technician) {
            DB::table('technician_locations')->insert([
                'user_id' => $technician->id, 'latitude' => -0.0263, 'longitude' => 109.3425,
                'accuracy' => 5.0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
