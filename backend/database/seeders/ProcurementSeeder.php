<?php

namespace Database\Seeders;

use App\Models\PdamOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ProcurementSeeder — modul PROC (Pengadaan & Vendor). Hanya tenant Canada (full).
 *
 * Mengisi: vendors, tenders, tender_bids, vendor_contracts, vendor_evaluations,
 * purchase_requests. Idempotent: dilewati bila vendors sudah ada.
 */
class ProcurementSeeder extends Seeder
{
    private const PROC_TENANTS = ['pdam-canada'];

    public function run(): void
    {
        if (DB::table('vendors')->exists()) {
            return;
        }

        foreach (self::PROC_TENANTS as $code) {
            $org = PdamOrganization::where('code', $code)->first();
            if (! $org) {
                continue;
            }
            $this->seedForTenant($org->id);
        }
    }

    private function seedForTenant(int $orgId): void
    {
        // ── Vendor ──
        $vendorDefs = [
            ['VND-001', 'PT Pipa Nusantara', 'material', 4.50],
            ['VND-002', 'CV Konstruksi Jaya', 'jasa_konstruksi', 4.20],
            ['VND-003', 'PT Teknik Pompa Andalan', 'peralatan', 3.90],
        ];
        $vendorIds = [];
        foreach ($vendorDefs as $i => [$code, $name, $category, $rating]) {
            $vendorIds[$code] = DB::table('vendors')->insertGetId([
                'pdam_org_id' => $orgId, 'code' => $code, 'name' => $name,
                'npwp' => '01.234.567.8-90'.$i.'.000', 'contact_name' => 'PIC '.$name,
                'phone' => '021700'.$i, 'email' => 'sales'.$i.'@vendor.co.id',
                'address' => 'Indonesia', 'category' => $category, 'rating' => $rating,
                'is_blacklisted' => false, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // ── Tender (selesai, ada pemenang) ──
        $tenderId = DB::table('tenders')->insertGetId([
            'pdam_org_id' => $orgId, 'tender_number' => 'TDR-'.$orgId.'-0001',
            'title' => 'Pengadaan Pipa Distribusi Zona Utara', 'category' => 'material',
            'budget_ceiling' => 500_000_000, 'publish_date' => now()->subDays(40)->toDateString(),
            'submission_deadline' => now()->subDays(25)->toDateString(),
            'award_date' => now()->subDays(20)->toDateString(), 'status' => 'awarded',
            'winner_vendor_id' => $vendorIds['VND-001'], 'final_price' => 465_000_000,
            'created_at' => now()->subDays(40), 'updated_at' => now()->subDays(20),
        ]);

        // ── Penawaran tender ──
        $bids = [
            ['VND-001', 465_000_000, 88.0, 92.0, 90.0, 1],
            ['VND-002', 480_000_000, 85.0, 85.0, 85.0, 2],
        ];
        foreach ($bids as [$vcode, $price, $tech, $priceScore, $total, $rank]) {
            DB::table('tender_bids')->insert([
                'pdam_org_id' => $orgId, 'tender_id' => $tenderId, 'vendor_id' => $vendorIds[$vcode],
                'bid_price' => $price, 'technical_proposal' => 'Proposal teknis '.$vcode,
                'technical_score' => $tech, 'price_score' => $priceScore, 'total_score' => $total,
                'rank' => $rank, 'created_at' => now()->subDays(24), 'updated_at' => now()->subDays(20),
            ]);
        }

        // ── Kontrak vendor ──
        $contractId = DB::table('vendor_contracts')->insertGetId([
            'pdam_org_id' => $orgId, 'vendor_id' => $vendorIds['VND-001'],
            'contract_number' => 'CTR-'.$orgId.'-0001', 'title' => 'Kontrak Pengadaan Pipa Distribusi',
            'start_date' => now()->subDays(18)->toDateString(),
            'end_date' => now()->addDays(72)->toDateString(), 'value' => 465_000_000,
            'status' => 'active', 'tender_id' => $tenderId,
            'created_at' => now()->subDays(18), 'updated_at' => now()->subDays(18),
        ]);

        // ── Evaluasi vendor ──
        DB::table('vendor_evaluations')->insert([
            'pdam_org_id' => $orgId, 'vendor_id' => $vendorIds['VND-001'], 'contract_id' => $contractId,
            'quality_score' => 4.5, 'delivery_score' => 4.0, 'price_score' => 4.2, 'compliance_score' => 4.8,
            'overall_score' => 4.4, 'comments' => 'Pengiriman tepat waktu, kualitas baik.',
            'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5),
        ]);

        // ── Purchase request ──
        $requester = DB::table('users')->where('pdam_org_id', $orgId)->first();
        $warehouse = DB::table('warehouses')->where('pdam_org_id', $orgId)->first();
        DB::table('purchase_requests')->insert([
            'pdam_org_id' => $orgId, 'pr_number' => 'PR-'.$orgId.'-0001',
            'requested_by' => $requester?->id ?? 0, 'warehouse_id' => $warehouse?->id,
            'status' => 'approved', 'required_date' => now()->addDays(20)->toDateString(),
            'estimated_total' => 75_000_000, 'notes' => 'Kebutuhan valve & fitting darurat.',
            'created_at' => now()->subDays(7), 'updated_at' => now()->subDays(3),
        ]);
    }
}
