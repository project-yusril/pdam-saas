<?php

namespace App\Support;

/**
 * RolePermissionPresets — pemetaan preset permission per role bawaan (PRD 6.B).
 * Dipakai saat provisioning tenant untuk mengisi izin awal tiap role.
 * admin_tenant TIDAK ada di sini karena otomatis mendapat SEMUA permission.
 * Role yang modulnya belum dibangun sengaja dikosongkan (diisi bertahap per fase).
 *
 * Gunakan '*' untuk memberi seluruh permission (tidak dipakai selain admin_tenant).
 */
class RolePermissionPresets
{
    /** @return array<string, string[]> code role → daftar kode permission */
    public static function map(): array
    {
        return [
            'compliance_officer' => [
                'core.privacy.purge',
            ],
            'director' => [
                'core.report.view', 'core.report.export',
                'bi.view.view', 'bi.schedule.view', 'bi.schedule.manage', 'bi.schedule.run', 'bi.schedule.download',
                'core.bill.view', 'core.payment.view', 'core.customer.view',
                'core.journal.view', 'core.installment.view', 'core.installment.approve_director',
                'wh.material.view', 'wh.stock.view', 'wh.transfer.view', 'wh.po.view', 'wh.po.approve',
                'srv.prospect.view', 'srv.survey.view', 'mtr.reading.view',
                'iam.user.view', 'iam.role.view',
                // ZONE — direktur lihat & kelola wilayah penuh
                'zone.zone.view', 'zone.zone.create', 'zone.zone.update', 'zone.zone.deactivate',
                'zone.employee.view', 'zone.employee.assign',
                'zone.dashboard.view',
                // METX — direktur lihat analytics meter
                'metx.meter.view', 'metx.anomaly.view', 'metx.dashboard.view',
                // AST — direktur lihat aset & dashboard
                'ast.asset.view', 'ast.depreciation.view', 'ast.dashboard.view',
                // GIS — direktur pantau peta pelanggan + jaringan perpipaan & NRW
                'gis.feature.view',
            ],

            'finance_head' => [
                'core.bill.view', 'core.bill.generate', 'core.bill.export',
                'core.payment.view', 'core.payment.create', 'core.payment.refund',
                'core.tariff.view', 'core.tariff.update',
                'core.report.view', 'core.report.export',
                'core.journal.view', 'core.journal.create',
                'core.installment.view', 'core.installment.create', 'core.installment.approve',
                'wh.po.view', 'wh.po.approve',
                // AST — kepala keuangan lihat aset, jalankan penyusutan
                'ast.asset.view', 'ast.depreciation.view', 'ast.depreciation.run', 'ast.dashboard.view',
            ],

            'finance_staff' => [
                'core.journal.view', 'core.journal.create',
                'core.report.view', 'core.payment.view', 'core.bill.view',
            ],
            'cashier' => [
                'core.payment.view', 'core.payment.create', 'core.bill.view',
            ],
            'customer_service' => [
                'core.customer.view', 'core.bill.view', 'core.payment.view', 'srv.prospect.view',
                'core.lifecycle.view',
            ],
            'hublang_head' => [
                'srv.prospect.view', 'srv.prospect.create', 'srv.prospect.assign', 'srv.prospect.reject', 'srv.prospect.pay',
                'srv.survey.view', 'core.customer.view',
                'srv.installation.view', 'srv.installation.activate',
                'core.installment.view', 'core.installment.create',
                'core.lifecycle.view', 'core.lifecycle.disconnect', 'core.lifecycle.reconnect', 'core.lifecycle.transfer_ownership',
            ],

            'survey_officer' => [
                'srv.prospect.view', 'srv.survey.view', 'srv.survey.submit',
            ],
            'survey_head' => [
                'srv.prospect.view', 'srv.survey.view', 'srv.survey.approve',
            ],
            'technical_head' => [
                'wh.po.view', 'wh.po.create', 'wh.po.approve',
                'wh.material.view', 'wh.stock.view', 'mtr.route.view',
                'srv.installation.view', 'srv.installation.schedule', 'srv.installation.complete',
                // METX — kepala teknik lihat meter & anomali + dashboard (tindak lanjut lapangan)
                'metx.meter.view', 'metx.meter.update', 'metx.anomaly.view', 'metx.anomaly.review', 'metx.dashboard.view',
                // GIS — jaringan perpipaan: pantau, tutup valve, insiden → WO
                'gis.feature.view', 'gis.feature.update', 'fsm.wo.view', 'fsm.wo.create', 'fsm.wo.assign',
            ],

            'installer_technician' => [
                'srv.survey.view', 'wh.material.view',
                'srv.installation.view', 'srv.installation.complete',
            ],

            'meter_office' => [
                'mtr.period.view', 'mtr.period.open', 'mtr.period.close',
                'mtr.route.view', 'mtr.route.create', 'mtr.route.update', 'mtr.route.delete', 'mtr.route.assign',
                'mtr.reading.view', 'mtr.reading.verify',
                // METX — koordinator baca meter kelola meter fisik & anomali penuh
                'metx.meter.view', 'metx.meter.create', 'metx.meter.update', 'metx.meter.assign', 'metx.meter.lifecycle',
                'metx.anomaly.view', 'metx.anomaly.scan', 'metx.anomaly.review', 'metx.anomaly.confirm', 'metx.anomaly.dismiss',
                'metx.dashboard.view',
            ],

            'meter_officer' => [
                'mtr.reading.view', 'mtr.reading.create', 'mtr.route.view',
            ],
            'warehouse_head' => [
                'wh.material.view', 'wh.material.create', 'wh.material.update', 'wh.material.delete',
                'wh.stock.view', 'wh.stock.adjust',
                'wh.transfer.view', 'wh.transfer.create', 'wh.transfer.approve',
                'wh.po.view', 'wh.po.create',
            ],
            'warehouse_staff' => [
                'wh.material.view', 'wh.stock.view', 'wh.transfer.view', 'wh.transfer.create',
            ],
            'procurement_staff' => [
                'wh.po.view', 'wh.po.create',
            ],
            'accountant' => [
                'core.journal.view', 'core.journal.create', 'core.report.view', 'core.report.export',
            ],
            'tax_officer' => [
                'core.report.view', 'core.report.export',
            ],
            'data_analyst' => [
                'bi.view.view', 'bi.schedule.view', 'bi.schedule.manage', 'bi.schedule.run', 'bi.schedule.download',
            ],
            'asset_manager' => [
                'wh.material.view', 'wh.stock.view',
                // AST — kelola aset penuh: register, kategori, mutasi, disposal, penyusutan
                'ast.asset.view', 'ast.asset.create', 'ast.asset.manage', 'ast.asset.dispose',
                'ast.depreciation.view', 'ast.depreciation.run', 'ast.dashboard.view',
            ],

            // Pelanggan: akses portal (lihat tagihan sendiri & bayar). Kepemilikan
            // data dibatasi di controller berdasarkan user login, bukan sekadar permission.
            'customer' => [
                'core.bill.view', 'core.payment.view', 'core.payment.create',
            ],

            // GIS — kelola penuh jaringan perpipaan + peta + insiden + work order
            'gis_operator' => [
                'gis.feature.view', 'gis.feature.create', 'gis.feature.update', 'gis.feature.delete',
                'fsm.wo.view', 'fsm.wo.create',
            ],
            // Teknis lapangan (preset utama digabung di blok atas; dispatcher lihat + WO)
            'field_dispatcher' => [
                'gis.feature.view', 'fsm.wo.view', 'fsm.wo.create', 'fsm.wo.assign',
            ],
            // Role berikut modulnya belum dibangun → preset kosong (diisi per fase):
            // production_head, lab_analyst, maintenance_technician,

            // field_dispatcher, field_supervisor, field_technician, hr_staff, hr_head,
            // gis_operator, dms_officer, call_agent, call_supervisor
        ];
    }
}
