<?php

namespace Database\Seeders;

use App\Models\PdamOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * PlatformCommerceSeeder — data komersial level platform (super admin).
 *
 * Mengisi: module_price_tiers (harga per skala), module_bundles(+items),
 * promos(+targets+redemptions), saas_invoices (tagihan langganan tenant).
 *
 * Idempotent: dilewati bila module_price_tiers sudah ada.
 */
class PlatformCommerceSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('module_price_tiers')->exists()) {
            return;
        }

        $this->seedPriceTiers();
        $this->seedBundles();
        $this->seedPromos();
        $this->seedSaasInvoices();
    }

    /** Harga berjenjang per jumlah pelanggan untuk tiap modul berbayar. */
    private function seedPriceTiers(): void
    {
        $modules = DB::table('modules')->where('is_default', false)->get();

        foreach ($modules as $module) {
            $base = (float) ($module->base_price_year ?: 12_000_000);

            $tiers = [
                [0, 10000, round($base, 2)],
                [10001, 50000, round($base * 1.5, 2)],
                [50001, null, round($base * 2.2, 2)],
            ];
            foreach ($tiers as [$min, $max, $price]) {
                DB::table('module_price_tiers')->insert([
                    'module_code' => $module->code,
                    'min_customers' => $min,
                    'max_customers' => $max,
                    'price_year' => $price,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    /** Paket bundling modul dengan harga khusus. */
    private function seedBundles(): void
    {
        $bundles = [
            ['STARTER', 'Paket Starter', 30_000_000, 'Modul dasar operasional PDAM kecil', ['SRV', 'MTR', 'BILL']],
            ['PRO', 'Paket Profesional', 75_000_000, 'Operasional + keuangan lengkap', ['SRV', 'MTR', 'BILL', 'FIN', 'CRM', 'WH']],
            ['ENTERPRISE', 'Paket Enterprise', 150_000_000, 'Seluruh modul termasuk IoT & analitik', null],
        ];

        foreach ($bundles as [$code, $name, $price, $desc, $moduleCodes]) {
            $bundleId = DB::table('module_bundles')->insertGetId([
                'code' => $code, 'name' => $name, 'price_year' => $price, 'description' => $desc,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $query = DB::table('modules');
            if ($moduleCodes !== null) {
                $query->whereIn('code', $moduleCodes);
            } else {
                $query->where('is_default', false); // enterprise = semua modul non-core
            }

            $moduleIds = $query->pluck('id');

            foreach ($moduleIds as $moduleId) {
                DB::table('module_bundle_items')->insert([
                    'module_bundle_id' => $bundleId, 'module_id' => $moduleId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    /** Promo diskon + target + redemption. */
    private function seedPromos(): void
    {
        $canada = PdamOrganization::where('code', 'pdam-canada')->first();

        // Promo aktif untuk semua tenant
        $promoAllId = DB::table('promos')->insertGetId([
            'code' => 'NEWYEAR2026', 'name' => 'Diskon Tahun Baru 2026',
            'description' => 'Diskon 15% untuk semua langganan modul.',
            'discount_type' => 'percent', 'discount_value' => 15,
            'scope_type' => 'all', 'target_type' => 'all_tenants',
            'max_discount_cap' => 20_000_000, 'usage_quota' => 100, 'used_count' => 1,
            'starts_at' => now()->subDays(30), 'ends_at' => now()->addDays(30), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Promo tertarget khusus Canada
        if ($canada) {
            $promoTargetId = DB::table('promos')->insertGetId([
                'code' => 'CANADA-LOYAL', 'name' => 'Loyalitas PDAM Canada',
                'description' => 'Diskon khusus tenant setia.', 'discount_type' => 'fixed',
                'discount_value' => 5_000_000, 'scope_type' => 'tenant', 'target_type' => 'specific',
                'usage_quota' => 1, 'used_count' => 1,
                'starts_at' => now()->subDays(10), 'ends_at' => now()->addDays(60), 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('promo_targets')->insert([
                'promo_id' => $promoTargetId, 'pdam_org_id' => $canada->id,
            ]);

            // Redemption tercatat
            DB::table('promo_redemptions')->insert([
                'promo_id' => $promoAllId, 'pdam_org_id' => $canada->id, 'module_code' => 'FIN',
                'original_price' => 20_000_000, 'discount_amount' => 3_000_000, 'final_price' => 17_000_000,
                'redeemed_at' => now()->subDays(5),
            ]);
        }
    }

    /** Tagihan langganan SaaS ke tiap tenant. */
    private function seedSaasInvoices(): void
    {
        $orgs = PdamOrganization::all();
        foreach ($orgs as $org) {
            foreach (['2026-04', '2026-05', '2026-06'] as $i => $period) {
                DB::table('saas_invoices')->insert([
                    'pdam_org_id' => $org->id, 'period' => $period,
                    'amount' => 25_000_000,
                    'status' => $i < 2 ? 'paid' : 'unpaid',
                    'paid_at' => $i < 2 ? now()->subMonths(2 - $i) : null,
                    'created_at' => now()->subMonths(3 - $i), 'updated_at' => now(),
                ]);
            }
        }
    }
}
