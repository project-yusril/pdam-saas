<?php

namespace Database\Seeders;

use Database\Seeders\Support\DemoGuard;
use Illuminate\Database\Seeder;

/**
 * DemoSeeder — SELURUH fixture demo (3 tenant Canada/Brazil/Sambas, user
 * per-role dengan password 12345678, data operasional & enterprise).
 *
 * Dilarang pada production (temuan2.md P1). Untuk development gunakan
 * `php artisan migrate --seed` (DatabaseSeeder otomatis memanggil ini hanya
 * pada environment non-production), atau `php artisan db:seed --class=DemoSeeder`.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DemoGuard::assertNonProduction('DemoSeeder');

        $this->call([
            DemoTenantSeeder::class,
            // Master keuangan per tenant: COA, 17 tarif Pontianak, billing settings.
            MasterFinanceSeeder::class,
            // Data operasional terintegrasi (pelanggan, gudang, tagihan, jurnal, neraca).
            OperationalDataSeeder::class,

            // ── Pelengkap tabel kosong (temuan.md) ──
            AddressSeeder::class,
            CustomerLifecycleSeeder::class,   // prospek, survey, isolir, balik nama
            CrmDetailSeeder::class,           // riwayat & feedback pengaduan
            MeterExtendedSeeder::class,       // METX: lifecycle, stok, anomali (Canada)
            NotificationSeeder::class,        // notifikasi in-app + chat
            BillingExtraSeeder::class,        // cicilan + penyesuaian tagihan
            WarehouseAdvancedSeeder::class,   // PO, transfer, opname, repair order
            FinanceEnterpriseSeeder::class,   // FIN+: invoice, bank, budget, pajak
            AssetSeeder::class,               // AST: aset tetap + penyusutan (sebelum MNT)
            ChemicalSeeder::class,            // CHEM: rantai bahan kimia IPA
            ProcurementSeeder::class,         // PROC: vendor, tender, kontrak
            FieldServiceSeeder::class,        // FSM: work order lapangan
            MaintenanceSeeder::class,         // MNT: pemeliharaan preventif (butuh AST)
            HrSeeder::class,                  // HR: kepegawaian & payroll
            DmsGisIntegrationSeeder::class,   // DMS, GIS, integrasi, call center
            SmartUtilitySeeder::class,        // Tier-3: IoT/SCADA, DMA/NRW, ML
            PlatformCommerceSeeder::class,    // price tier, bundle, promo, saas invoice
            SambasTenantSeeder::class,        // PDAM Kabupaten Sambas (Kalbar)

            // Demo super admin platform (password lemah) — selalu paling akhir
            // agar mudah diidentifikasi sebagai fixture, bukan provisioning.
            PlatformAdminSeeder::class,
        ]);
    }
}
