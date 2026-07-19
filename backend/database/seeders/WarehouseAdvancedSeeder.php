<?php

namespace Database\Seeders;

use App\Models\PdamOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * WarehouseAdvancedSeeder — modul WH lanjutan.
 *
 * Mengisi: purchase_orders, stock_transfers (+items), stock_adjustments, repair_orders.
 * Semua transaksi non-posting GL (draft/completed tanpa journal_entry_id) agar neraca
 * inti tetap balance. Idempotent: dilewati bila purchase_orders sudah ada.
 */
class WarehouseAdvancedSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('purchase_orders')->exists()) {
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
        $materials = DB::table('materials')->where('pdam_org_id', $orgId)->get();
        $supplier = DB::table('suppliers')->where('pdam_org_id', $orgId)->first();
        $requester = DB::table('users')->where('pdam_org_id', $orgId)->first();
        $warehouses = DB::table('warehouses')->where('pdam_org_id', $orgId)->orderBy('id')->get();

        if ($materials->isEmpty() || ! $requester) {
            return;
        }

        // ── Purchase Order (satu contoh, status draft) ──
        $poItems = $materials->take(3)->map(fn ($m) => [
            'material_id' => $m->id,
            'qty' => 100,
            'price' => (float) $m->last_price,
        ])->values()->all();
        $poTotal = collect($poItems)->sum(fn ($it) => $it['qty'] * $it['price']);

        DB::table('purchase_orders')->insert([
            'pdam_org_id' => $orgId,
            'po_number' => sprintf('PO-%d-0001', $orgId),
            'supplier_id' => $supplier?->id,
            'requested_by' => $requester->id,
            'items' => json_encode($poItems),
            'total_estimated_price' => $poTotal,
            'urgency' => 'normal',
            'status' => 'draft',
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);

        // ── Stock Transfer (main → buffer) bila ada >=2 gudang ──
        if ($warehouses->count() >= 2) {
            $from = $warehouses[0];
            $to = $warehouses[1];

            $transferId = DB::table('stock_transfers')->insertGetId([
                'pdam_org_id' => $orgId,
                'transfer_number' => sprintf('TRF-%d-0001', $orgId),
                'transfer_type' => 'main_to_buffer',
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'reason' => 'stock_imbalance',
                'status' => 'completed',
                'requested_by' => $requester->id,
                'approved_by' => $requester->id,
                'received_by' => $requester->id,
                'notes' => 'Pemerataan stok antar gudang.',
                'completed_at' => now()->subDays(3),
                'created_at' => now()->subDays(4),
                'updated_at' => now()->subDays(3),
            ]);

            foreach ($materials->take(2) as $m) {
                DB::table('stock_transfer_items')->insert([
                    'pdam_org_id' => $orgId,
                    'transfer_id' => $transferId,
                    'material_id' => $m->id,
                    'quantity_requested' => 20,
                    'quantity_received' => 20,
                    'created_at' => now()->subDays(4),
                    'updated_at' => now()->subDays(3),
                ]);
            }
        }

        // ── Stock Adjustment (opname, tanpa posting GL) ──
        $mainWarehouse = $warehouses->first();
        $adjMaterial = $materials->first();
        if ($mainWarehouse && $adjMaterial) {
            $sysStock = (float) (DB::table('material_stocks')
                ->where('material_id', $adjMaterial->id)
                ->where('warehouse_id', $mainWarehouse->id)
                ->value('current_stock') ?? 100);
            $physical = $sysStock - 2; // selisih minus 2 unit

            DB::table('stock_adjustments')->insert([
                'pdam_org_id' => $orgId,
                'material_id' => $adjMaterial->id,
                'warehouse_id' => $mainWarehouse->id,
                'system_stock' => $sysStock,
                'physical_stock' => $physical,
                'difference' => $physical - $sysStock,
                'reason' => 'opname',
                'adjusted_by' => $requester->id,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]);
        }

        // ── Repair Order ──
        $customer = DB::table('customers')->where('pdam_org_id', $orgId)->first();
        DB::table('repair_orders')->insert([
            'pdam_org_id' => $orgId,
            'order_number' => sprintf('RO-%d-0001', $orgId),
            'customer_id' => $customer?->id,
            'description' => 'Perbaikan kebocoran pipa dinas depan rumah pelanggan.',
            'materials_used' => json_encode([
                ['material_id' => $adjMaterial?->id, 'qty' => 2],
            ]),
            'warehouse_id' => $mainWarehouse?->id,
            'status' => 'completed',
            'created_by' => $requester->id,
            'completed_at' => now()->subDay(),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDay(),
        ]);
    }
}
