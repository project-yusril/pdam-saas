<?php

namespace Database\Seeders;

use App\Models\BillingSetting;
use App\Models\ChartOfAccount;
use App\Models\PdamOrganization;
use App\Models\TariffCategory;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;

/**
 * MasterFinanceSeeder — seed data keuangan inti per tenant:
 *  - Chart of Accounts standar PDAM (termasuk Persediaan Material sebagai ASET)
 *  - 17 golongan tarif + tier (data PDAM Tirta Khatulistiwa Pontianak, PRD 10.2)
 *  - billing_settings default
 *
 * Idempotent: aman dijalankan ulang (cek existing per tenant).
 */
class MasterFinanceSeeder extends Seeder
{
    /** COA standar. [code, name, type, normal_balance] */
    private const ACCOUNTS = [
        ['1-001', 'Kas / Bank', 'ASSET', 'DEBIT'],
        ['1-002', 'Piutang Pelanggan', 'ASSET', 'DEBIT'],
        ['1-003', 'Persediaan Material', 'ASSET', 'DEBIT'],   // material = aset, bukan biaya
        ['1-004', 'Aset Jaringan / Instalasi', 'ASSET', 'DEBIT'],
        ['2-001', 'Utang Usaha', 'LIABILITY', 'KREDIT'],
        ['3-001', 'Modal / Ekuitas', 'EQUITY', 'KREDIT'],
        ['4-001', 'Pendapatan Air', 'REVENUE', 'KREDIT'],
        ['4-002', 'Pendapatan Pemasangan Baru', 'REVENUE', 'KREDIT'],
        ['4-003', 'Pendapatan Penyambungan Kembali', 'REVENUE', 'KREDIT'],
        ['4-004', 'Pendapatan Denda', 'REVENUE', 'KREDIT'],
        ['5-001', 'Beban Material Perbaikan', 'EXPENSE', 'DEBIT'],
        ['5-002', 'Beban Kerugian Persediaan', 'EXPENSE', 'DEBIT'],

        // ── Aset Tetap & Penyusutan (Fase 7 AST) ─────────────────────
        ['1-100', 'Tanah', 'ASSET', 'DEBIT'],
        ['1-110', 'Bangunan & Instalasi Pengolahan Air (IPA)', 'ASSET', 'DEBIT'],
        ['1-120', 'Mesin & Pompa', 'ASSET', 'DEBIT'],
        ['1-130', 'Jaringan Pipa Transmisi/Distribusi', 'ASSET', 'DEBIT'],
        ['1-140', 'Kendaraan', 'ASSET', 'DEBIT'],
        ['1-150', 'Inventaris Kantor', 'ASSET', 'DEBIT'],
        ['1-160', 'Meter Induk', 'ASSET', 'DEBIT'],
        ['1-180', 'Konstruksi Dalam Pengerjaan (CIP)', 'ASSET', 'DEBIT'],
        ['1-190', 'Akumulasi Penyusutan', 'ASSET', 'KREDIT'], // kontra-aset
        ['4-005', 'Pendapatan Pelepasan Aset', 'REVENUE', 'KREDIT'],
        ['5-101', 'Beban Penyusutan', 'EXPENSE', 'DEBIT'],
        ['5-102', 'Rugi Pelepasan Aset', 'EXPENSE', 'DEBIT'],
    ];

    /**
     * 17 golongan: [code, name, group_type, [tier1_0_10, tier2_10_20, tier3_gt20]].
     * Sumber tarif: PRD 10.2 (PDAM Pontianak).
     */
    private const TARIFFS = [
        ['1A', 'Sosial Umum', 'sosial', [800, 1900, 1900]],
        ['1B', 'Sosial Khusus A', 'sosial', [1000, 2200, 2200]],
        ['1C', 'Sosial Khusus B', 'sosial', [1300, 2400, 2400]],
        ['2A1', 'Rumah Tangga Sederhana', 'rumah_tangga', [1800, 3400, 3400]],
        ['2A2', 'Rumah Tangga Semi Permanen', 'rumah_tangga', [2900, 5000, 5300]],
        ['2A3', 'Rumah Tangga Permanen', 'rumah_tangga', [3200, 5500, 6200]],
        ['2D', 'Rumah Tangga Daerah Perdagangan Pinggir Jalan', 'rumah_tangga', [3500, 6000, 6500]],
        ['2B', 'Rumah Tangga Permanen Mandiri', 'rumah_tangga', [5000, 7400, 8200]],
        ['2F', 'Instansi Pemerintah / Kedutaan / Konsulat', 'khusus', [6500, 8000, 8500]],
        ['3A', 'Niaga Kecil', 'niaga', [5200, 8000, 8600]],
        ['3B', 'Niaga Menengah', 'niaga', [6000, 8500, 9000]],
        ['3C', 'Niaga Besar', 'niaga', [6500, 10500, 11500]],
        ['4A', 'Industri Kecil', 'industri', [5500, 8700, 9200]],
        ['4B', 'Industri Menengah', 'industri', [6000, 9000, 10000]],
        ['4C', 'Industri Besar', 'industri', [6500, 11000, 12000]],
        ['5A', 'Pelabuhan', 'khusus', [40000, 50000, 60000]],
        ['5B', 'Mobil Tangki', 'khusus', [30000, 30000, 30000]],
    ];

    public function run(): void
    {
        $orgs = PdamOrganization::query()->get();

        foreach ($orgs as $org) {
            TenantContext::set($org->id);

            $this->seedAccounts();
            $this->seedTariffs();
            $this->seedBillingSetting($org->id);

            TenantContext::clear();
        }
    }

    private function seedAccounts(): void
    {
        foreach (self::ACCOUNTS as [$code, $name, $type, $normal]) {
            ChartOfAccount::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => $type, 'normal_balance' => $normal, 'is_active' => true],
            );
        }
    }

    private function seedTariffs(): void
    {
        foreach (self::TARIFFS as [$code, $name, $group, $prices]) {
            $category = TariffCategory::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'group_type' => $group,
                    // Komponen tetap contoh (bisa dikonfigurasi Kabag Keuangan)
                    'abonemen' => $group === 'rumah_tangga' ? 5000 : 0,
                    'meter_maintenance_fee' => 2500,
                    'admin_fee' => 2500,
                    'minimum_usage_m3' => 10,
                    'is_active' => true,
                ],
            );

            if ($category->tiers()->exists()) {
                continue;
            }

            // Tier bertingkat: (0..10], (10..20], (20..~)
            $bounds = [[0, 10], [10, 20], [20, null]];
            foreach ($bounds as $i => [$min, $max]) {
                $category->tiers()->create([
                    'pdam_org_id' => $category->pdam_org_id,
                    'tier_order' => $i + 1,
                    'min_usage' => $min,
                    'max_usage' => $max,
                    'price_per_m3' => $prices[$i],
                    'effective_date' => '2026-01-01',
                ]);
            }
        }
    }

    private function seedBillingSetting(int $orgId): void
    {
        BillingSetting::firstOrCreate(
            ['pdam_org_id' => $orgId],
            [
                'penalty_flat' => 5000,
                'penalty_percent' => 2.0,
                'due_day' => 20,
                'isolir_after_months' => 3,
            ],
        );
    }
}
