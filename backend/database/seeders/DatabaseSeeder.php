<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * DatabaseSeeder — seed data platform (Fase 0).
 * Data tenant dibuat via TenantProvisioningService, bukan di sini.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlatformAdminSeeder::class,
            ModuleSeeder::class,
            RoleTemplateSeeder::class,
            PermissionSeeder::class,
            // Data demo (2 tenant + user per role). Boleh dihapus untuk produksi.
            DemoTenantSeeder::class,
            // Master keuangan per tenant: COA, 17 tarif Pontianak, billing settings.
            MasterFinanceSeeder::class,
            // Data operasional terintegrasi (pelanggan, gudang, tagihan, jurnal, neraca).
            // Canada = data Pontianak (27 modul). Brazil = data Surabaya (sebagian modul).
            OperationalDataSeeder::class,

            // ── Pelengkap tabel kosong (temuan.md) ──
            // Fase 1: master alamat berjenjang + detail rute meter (kedua tenant).
            AddressSeeder::class,
            // Fase 2: melengkapi modul aktif (kedua tenant).
            CustomerLifecycleSeeder::class,   // prospek, survey, isolir, balik nama
            CrmDetailSeeder::class,           // riwayat & feedback pengaduan
            MeterExtendedSeeder::class,       // METX: lifecycle, stok, anomali (Canada)
            NotificationSeeder::class,        // notifikasi in-app + chat
            BillingExtraSeeder::class,        // cicilan + penyesuaian tagihan
            WarehouseAdvancedSeeder::class,   // PO, transfer, opname, repair order
            // Fase 3: modul enterprise (mayoritas hanya tenant Canada).
            FinanceEnterpriseSeeder::class,   // FIN+: invoice, bank, budget, pajak
            AssetSeeder::class,               // AST: aset tetap + penyusutan (sebelum MNT)
            ChemicalSeeder::class,            // CHEM: rantai bahan kimia IPA
            ProcurementSeeder::class,         // PROC: vendor, tender, kontrak
            FieldServiceSeeder::class,        // FSM: work order lapangan
            MaintenanceSeeder::class,         // MNT: pemeliharaan preventif (butuh AST)
            HrSeeder::class,                  // HR: kepegawaian & payroll
            DmsGisIntegrationSeeder::class,   // DMS, GIS, integrasi, call center
            SmartUtilitySeeder::class,        // Tier-3: IoT/SCADA, DMA/NRW, ML
            // Data komersial level platform (super admin).
            PlatformCommerceSeeder::class,    // price tier, bundle, promo, saas invoice
            // Tenant demo pdam-sambas: 27 modul LENGKAP, 3000 pelanggan, integrasi penuh.
            SambasTenantSeeder::class,        // PDAM Kabupaten Sambas (Kalbar)
        ]);
    }
}

