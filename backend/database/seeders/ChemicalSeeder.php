<?php

namespace Database\Seeders;

use App\Models\PdamOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ChemicalSeeder — modul CHEM (Bahan Kimia IPA). Hanya tenant Canada (full).
 *
 * Mengisi seluruh rantai bahan kimia: chemicals, chemical_suppliers,
 * chemical_purchase_requests, chemical_receipts(+items), chemical_qc_tests,
 * chemical_stocks, chemical_transactions, chemical_usages, chemical_forecasts,
 * chemical_stock_opnames. Idempotent: dilewati bila chemicals sudah ada.
 */
class ChemicalSeeder extends Seeder
{
    private const CHEM_TENANTS = ['pdam-canada'];

    public function run(): void
    {
        if (DB::table('chemicals')->exists()) {
            return;
        }

        foreach (self::CHEM_TENANTS as $code) {
            $org = PdamOrganization::where('code', $code)->first();
            if (! $org) {
                continue;
            }
            $this->seedForTenant($org->id);
        }
    }

    private function seedForTenant(int $orgId): void
    {
        $warehouse = DB::table('warehouses')->where('pdam_org_id', $orgId)->first();

        // ── Bahan kimia ──
        $chemDefs = [
            ['PAC', 'Poly Aluminium Chloride', 'kg', 25.0000, 50.0000],
            ['KAPORIT', 'Kaporit (Kalsium Hipoklorit)', 'kg', 3.0000, 8.0000],
            ['TAWAS', 'Aluminium Sulfat (Tawas)', 'kg', 30.0000, 60.0000],
        ];
        $chemIds = [];
        foreach ($chemDefs as $i => [$code, $name, $unit, $dosage, $threshold]) {
            $chemIds[$code] = DB::table('chemicals')->insertGetId([
                'pdam_org_id' => $orgId, 'code' => $code, 'name' => $name, 'unit' => $unit,
                'standard_dosage' => $dosage, 'safety_threshold' => $threshold,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // ── Supplier bahan kimia ──
        $supplierId = DB::table('chemical_suppliers')->insertGetId([
            'pdam_org_id' => $orgId, 'name' => 'PT Kimia Tirta Sejahtera',
            'contact_name' => 'Bpk. Hendra', 'phone' => '021555888', 'email' => 'sales@kimiatirta.co.id',
            'address' => 'Jakarta', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Purchase request ──
        DB::table('chemical_purchase_requests')->insert([
            'pdam_org_id' => $orgId, 'pr_number' => 'CPR-' . $orgId . '-0001',
            'chemical_id' => $chemIds['PAC'], 'supplier_id' => $supplierId,
            'quantity' => 1000, 'unit' => 'kg',
            'required_date' => now()->addDays(14)->toDateString(),
            'status' => 'approved', 'approved_at' => now()->subDays(2),
            'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(2),
        ]);

        // ── Penerimaan + item + QC ──
        $receiptId = DB::table('chemical_receipts')->insertGetId([
            'pdam_org_id' => $orgId, 'receipt_number' => 'CRC-' . $orgId . '-0001',
            'supplier_id' => $supplierId, 'receipt_date' => now()->subDays(3)->toDateString(),
            'batch_number' => 'BATCH-PAC-2606', 'expiry_date' => now()->addYear()->toDateString(),
            'status' => 'accepted', 'total_cost' => 8_500_000,
            'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3),
        ]);
        DB::table('chemical_receipt_items')->insert([
            'pdam_org_id' => $orgId, 'chemical_receipt_id' => $receiptId,
            'chemical_id' => $chemIds['PAC'], 'quantity' => 1000, 'unit' => 'kg', 'unit_cost' => 8_500,
            'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3),
        ]);
        DB::table('chemical_qc_tests')->insert([
            'pdam_org_id' => $orgId, 'chemical_receipt_id' => $receiptId,
            'test_name' => 'Kadar Al2O3', 'result_value' => 10.5, 'result_unit' => '%',
            'verdict' => 'pass', 'notes' => 'Memenuhi spesifikasi minimal 10%.',
            'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3),
        ]);

        // ── Stok + transaksi ──
        $stockId = DB::table('chemical_stocks')->insertGetId([
            'pdam_org_id' => $orgId, 'chemical_id' => $chemIds['PAC'], 'warehouse_id' => $warehouse?->id,
            'batch_number' => 'BATCH-PAC-2606', 'expiry_date' => now()->addYear()->toDateString(),
            'quantity' => 850, 'unit' => 'kg', 'unit_cost' => 8_500,
            'created_at' => now()->subDays(3), 'updated_at' => now(),
        ]);
        DB::table('chemical_transactions')->insert([
            [
                'pdam_org_id' => $orgId, 'chemical_stock_id' => $stockId, 'type' => 'in',
                'quantity' => 1000, 'balance_after' => 1000, 'reference_type' => 'receipt',
                'reference_id' => $receiptId, 'notes' => 'Penerimaan awal.',
                'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3),
            ],
            [
                'pdam_org_id' => $orgId, 'chemical_stock_id' => $stockId, 'type' => 'out',
                'quantity' => 150, 'balance_after' => 850, 'reference_type' => 'usage',
                'reference_id' => null, 'notes' => 'Pemakaian dosing IPA.',
                'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
            ],
        ]);

        // ── Pemakaian harian ──
        foreach ([['2026-06-05', 50, 12000], ['2026-06-06', 55, 12500], ['2026-06-07', 45, 11000]] as [$date, $qty, $water]) {
            DB::table('chemical_usages')->insert([
                'pdam_org_id' => $orgId, 'chemical_id' => $chemIds['PAC'], 'usage_date' => $date,
                'quantity' => $qty, 'unit' => 'kg', 'water_produced_m3' => $water,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // ── Forecast ──
        DB::table('chemical_forecasts')->insert([
            'pdam_org_id' => $orgId, 'chemical_id' => $chemIds['PAC'], 'forecast_period' => '2026-07',
            'forecasted_quantity' => 1600, 'confidence_low' => 1400, 'confidence_high' => 1800,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Stock opname ──
        DB::table('chemical_stock_opnames')->insert([
            'pdam_org_id' => $orgId, 'chemical_stock_id' => $stockId,
            'opname_date' => now()->subDays(1)->toDateString(),
            'system_quantity' => 850, 'actual_quantity' => 848, 'difference' => -2,
            'reason' => 'Susut penimbangan.', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
