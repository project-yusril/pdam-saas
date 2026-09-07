<?php

namespace Database\Seeders;

use App\Models\PdamOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * AssetSeeder — modul AST (Aset Tetap & Penyusutan). Hanya tenant Canada (full).
 *
 * Mengisi: asset_categories, fixed_assets, depreciation_entries, asset_movements, asset_disposals.
 * Penyusutan dihitung garis lurus; entri penyusutan TIDAK posting GL (journal_entry_id null)
 * agar neraca inti tetap balance. Idempotent: dilewati bila fixed_assets sudah ada.
 */
class AssetSeeder extends Seeder
{
    private const AST_TENANTS = ['pdam-canada'];

    public function run(): void
    {
        if (DB::table('fixed_assets')->exists()) {
            return;
        }

        foreach (self::AST_TENANTS as $code) {
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

        // ── Kategori aset ──
        $categories = [
            ['TANAH', 'Tanah', 0, false, 0, '1-201', null, null],
            ['BANGUNAN', 'Bangunan & Gedung', 240, true, 0, '1-202', '1-212', '5-101'],
            ['MESIN', 'Mesin & Pompa', 120, true, 0, '1-203', '1-213', '5-102'],
            ['KENDARAAN', 'Kendaraan Dinas', 96, true, 0, '1-204', '1-214', '5-103'],
        ];
        $catIds = [];
        foreach ($categories as [$code, $name, $life, $depreciable, $rate, $assetAcc, $accumAcc, $expAcc]) {
            $catIds[$code] = DB::table('asset_categories')->insertGetId([
                'pdam_org_id' => $orgId, 'code' => $code, 'name' => $name,
                'useful_life_months' => $life ?: 60, 'depreciation_method' => 'straight_line',
                'declining_rate' => $rate, 'is_depreciable' => $depreciable,
                'asset_account_code' => $assetAcc, 'accumulation_account_code' => $accumAcc,
                'expense_account_code' => $expAcc, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // ── Aset tetap ──
        $assets = [
            ['TANAH', 'AST-001', 'Tanah Kantor Pusat', '2020-01-15', 2_000_000_000, 0, 0, false],
            ['BANGUNAN', 'AST-002', 'Gedung Kantor Pusat', '2020-06-01', 1_500_000_000, 240, 50_000_000, true],
            ['MESIN', 'AST-003', 'Pompa Distribusi Utama', '2023-03-10', 800_000_000, 120, 40_000_000, true],
            ['KENDARAAN', 'AST-004', 'Truk Tangki Air', '2024-08-20', 450_000_000, 96, 45_000_000, true],
        ];

        foreach ($assets as [$catCode, $code, $name, $acqDate, $cost, $life, $residual, $depreciable]) {
            $monthsElapsed = $life > 0 ? min(6, $life) : 0; // asumsi 6 bulan berjalan
            $monthlyDep = $depreciable && $life > 0 ? round(($cost - $residual) / $life, 2) : 0;
            $accumulated = $monthlyDep * $monthsElapsed;
            $bookValue = $cost - $accumulated;

            $assetId = DB::table('fixed_assets')->insertGetId([
                'pdam_org_id' => $orgId, 'asset_category_id' => $catIds[$catCode],
                'zone_id' => $zone?->id, 'code' => $code, 'name' => $name,
                'acquisition_date' => $acqDate, 'acquisition_cost' => $cost,
                'residual_value' => $residual, 'useful_life_months' => $life ?: 60,
                'depreciation_method' => 'straight_line', 'declining_rate' => 0,
                'is_depreciable' => $depreciable, 'accumulated_depreciation' => $accumulated,
                'book_value' => $bookValue, 'source' => 'beli', 'status' => 'aktif',
                'last_depreciated_period' => $depreciable ? '2026-06' : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            // Entri penyusutan bulanan (2026-04 s/d 2026-06) untuk aset yg disusutkan
            if ($depreciable && $monthlyDep > 0) {
                $runningAccum = $accumulated - ($monthlyDep * 3);
                foreach (['2026-04', '2026-05', '2026-06'] as $period) {
                    $runningAccum += $monthlyDep;
                    DB::table('depreciation_entries')->insert([
                        'pdam_org_id' => $orgId, 'fixed_asset_id' => $assetId, 'period' => $period,
                        'depreciation_amount' => $monthlyDep,
                        'accumulated_after' => $runningAccum,
                        'book_value_after' => $cost - $runningAccum,
                        'journal_entry_id' => null,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }

        // ── Mutasi aset (pindah zona) ──
        $movable = DB::table('fixed_assets')->where('pdam_org_id', $orgId)->where('code', 'AST-004')->first();
        $zone2 = DB::table('zones')->where('pdam_org_id', $orgId)->where('id', '!=', $zone?->id)->first();
        if ($movable && $zone2) {
            DB::table('asset_movements')->insert([
                'pdam_org_id' => $orgId, 'fixed_asset_id' => $movable->id,
                'from_zone_id' => $zone?->id, 'to_zone_id' => $zone2->id,
                'from_location' => 'Kantor Pusat', 'to_location' => 'Kantor '.$zone2->name,
                'moved_at' => now()->subDays(10)->toDateString(),
                'reason' => 'Realokasi armada ke zona baru', 'performed_by' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // ── Pelepasan aset (contoh aset rusak) ──
        $disposable = DB::table('fixed_assets')->where('pdam_org_id', $orgId)->where('code', 'AST-003')->first();
        if ($disposable) {
            DB::table('asset_disposals')->insert([
                'pdam_org_id' => $orgId, 'fixed_asset_id' => $disposable->id,
                'disposal_type' => 'rusak', 'disposal_date' => now()->subDays(2)->toDateString(),
                'sale_value' => 0, 'book_value_at_disposal' => $disposable->book_value,
                'gain_loss' => -1 * (float) $disposable->book_value,
                'reason' => 'Pompa terbakar akibat korsleting', 'journal_entry_id' => null,
                'performed_by' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
