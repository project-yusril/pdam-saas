<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * RoleTemplateSeeder — 34 role bawaan (PRD 6.B.0).
 * Disimpan sebagai TEMPLATE global (pdam_org_id = null, is_system_default = true).
 * Saat provisioning tenant, template ini disalin/di-clone menjadi role milik tenant.
 */
class RoleTemplateSeeder extends Seeder
{
    /** Registry role master (PRD 6.B.0). */
    public const ROLES = [
        'super_admin' => 'Super-Admin (Platform Owner)',
        'admin_tenant' => 'Admin PDAM',
        'compliance_officer' => 'Petugas Kepatuhan / Perlindungan Data',
        'director' => 'Direktur',
        'finance_head' => 'Kabag Keuangan',
        'finance_staff' => 'Staf Keuangan',
        'cashier' => 'Kasir / Loket Pembayaran',
        'customer_service' => 'Customer Service',
        'customer' => 'Pelanggan',
        'hublang_head' => 'Kepala Hublang',
        'survey_officer' => 'Petugas Survey',
        'survey_head' => 'Kepala Survey',
        'technical_head' => 'Kepala Teknik',
        'installer_technician' => 'Teknisi Pemasangan',
        'meter_office' => 'Koordinator Baca Meter (Kantor)',
        'meter_officer' => 'Petugas Baca Meter',
        'warehouse_head' => 'Kepala Gudang',
        'warehouse_staff' => 'Staf Gudang / Staf Wilayah',
        'procurement_staff' => 'Staf/Panitia Pengadaan',
        'production_head' => 'Kepala Produksi / Operator IPA',
        'lab_analyst' => 'Analis Lab / QC',
        'accountant' => 'Akuntan / Staf Akuntansi',
        'tax_officer' => 'Staf Pajak',
        'asset_manager' => 'Manajer Aset',
        'maintenance_technician' => 'Teknisi Pemeliharaan',
        'field_dispatcher' => 'Dispatcher Lapangan',
        'field_supervisor' => 'Supervisor Lapangan',
        'field_technician' => 'Teknisi Lapangan (WO)',
        'hr_staff' => 'Staf HR',
        'hr_head' => 'Kepala HR',
        'gis_operator' => 'Operator GIS',
        'dms_officer' => 'Petugas Arsip/Dokumen',
        'call_agent' => 'Agent Call Center',
        'call_supervisor' => 'Supervisor Call Center',
        'data_analyst' => 'Analis Data / BI',
    ];

    public function run(): void
    {
        foreach (self::ROLES as $code => $name) {
            Role::updateOrCreate(
                ['pdam_org_id' => null, 'code' => $code],
                ['name' => $name, 'is_system_default' => true]
            );
        }
    }
}
