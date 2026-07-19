<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * PermissionSeeder — permission granular pola modul.resource.aksi (PRD 4.D.3).
 * Fase 0: seed set inti CORE + beberapa modul kunci. Modul lain menyusul per fase.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // [module_code, resource, [actions]]
        $map = [
            // IAM — manajemen user & role dalam tenant (bagian CORE, tanpa gerbang modul)
            ['IAM', 'user', ['view', 'manage']],
            ['IAM', 'role', ['view', 'manage']],
            // CORE

            ['CORE', 'customer',    ['view', 'create', 'update', 'delete']],
            ['CORE', 'bill',        ['view', 'generate', 'export']],
            ['CORE', 'payment',     ['view', 'create', 'refund']],
            ['CORE', 'tariff',      ['view', 'manage']],
            ['CORE', 'report',      ['view', 'export']],
            ['CORE', 'user',        ['view', 'create', 'update', 'delete']],
            ['CORE', 'role',        ['view', 'create', 'update', 'delete']],
            ['CORE', 'address',     ['view', 'create', 'update', 'delete']],
            ['CORE', 'journal',     ['view', 'create']],
            ['CORE', 'installment', ['view', 'create', 'approve', 'approve_director', 'waive_penalty']],
            ['CORE', 'lifecycle',   ['view', 'disconnect', 'reconnect', 'transfer_ownership']],
            ['CORE', 'privacy',     ['purge']],

            // WH
            ['WH', 'material',  ['view', 'create', 'update', 'delete']],
            ['WH', 'stock',     ['view', 'adjust']],
            ['WH', 'transfer',  ['view', 'create', 'approve']],
            ['WH', 'po',        ['view', 'create', 'approve']],
            // MTR
            ['MTR', 'route',     ['view', 'create', 'update', 'delete', 'assign']],
            ['MTR', 'period',    ['view', 'open', 'close']],
            ['MTR', 'reading',   ['view', 'create', 'verify']],
            ['MTR', 'dashboard', ['view']],
            // SRV
            ['SRV', 'prospect', ['view', 'create', 'assign', 'reject', 'pay']],
            ['SRV', 'survey',   ['view', 'submit', 'approve']],
            ['SRV', 'installation', ['view', 'schedule', 'complete', 'activate']],

            // ZONE — manajemen wilayah/cabang (Fase 2)
            ['ZONE', 'zone',      ['view', 'create', 'update', 'deactivate']],
            ['ZONE', 'employee',  ['view', 'assign']],
            ['ZONE', 'dashboard', ['view']],

            // METX — Meter Analytics (Fase 6)
            ['METX', 'meter',     ['view', 'create', 'update', 'assign', 'lifecycle']],
            ['METX', 'anomaly',   ['view', 'scan', 'review', 'confirm', 'dismiss']],
            ['METX', 'dashboard', ['view']],

            // AST — Aset Tetap & Penyusutan (Fase 7)
            ['AST', 'asset',        ['view', 'create', 'manage', 'dispose']],
            ['AST', 'depreciation', ['view', 'run']],
            ['AST', 'dashboard',    ['view']],

            // CRM — Pengaduan & CRM (Fase 9)
            ['CRM', 'complaint', ['view', 'create', 'assign', 'resolve']],

            // C360 — Customer 360 View (Fase 10)
            ['C360', 'view', ['view']],

            // BILL+ — Advanced Billing (Fase 11)
            ['BILL', 'adjust', ['adjust']],
            ['BILL', 'audit', ['audit']],

            // FIN+ — Keuangan Advance (Fase 8)
            ['FIN', 'ar', ['view', 'manage']],
            ['FIN', 'ap', ['view', 'manage']],
            ['FIN', 'tax', ['view', 'manage']],
            ['FIN', 'budget', ['view', 'manage']],
            ['FIN', 'bank', ['view', 'manage']],

            // CHEM — Chemical Management (Fase 13)
            ['CHEM', 'chemical', ['view', 'manage']],
            ['CHEM', 'supplier', ['view', 'manage']],
            ['CHEM', 'pr', ['view', 'create', 'approve']],
            ['CHEM', 'receipt', ['view', 'create']],
            ['CHEM', 'qc', ['view', 'test']],
            ['CHEM', 'usage', ['view', 'record']],
            ['CHEM', 'forecast', ['view']],
            ['CHEM', 'opname', ['view', 'create']],
            ['CHEM', 'dashboard', ['view']],

            // PROC — Procurement (Fase 14)
            ['PROC', 'vendor', ['view', 'create', 'update', 'evaluate']],
            ['PROC', 'tender', ['view', 'create', 'evaluate', 'award']],
            ['PROC', 'contract', ['view', 'create', 'terminate']],
            ['PROC', 'pr', ['view', 'create', 'approve']],

            // FSM — Field Service (Fase 15)
            ['FSM', 'wo', ['view', 'create', 'assign', 'complete']],
            ['FSM', 'technician', ['view', 'dispatch', 'track']],
            ['FSM', 'dashboard', ['view']],

            // MNT — Maintenance (Fase 16)
            ['MNT', 'schedule', ['view', 'manage']],
            ['MNT', 'record', ['view', 'create']],
            ['MNT', 'dashboard', ['view']],

            // HR — HR Management (Fase 17)
            ['HR', 'employee', ['view', 'create', 'update', 'delete']],
            ['HR', 'attendance', ['view', 'manage']],
            ['HR', 'shift', ['view', 'manage']],
            ['HR', 'overtime', ['view', 'request', 'approve']],
            ['HR', 'leave', ['view', 'request', 'approve']],
            ['HR', 'payroll', ['view', 'run', 'approve']],
            ['HR', 'training', ['view', 'manage']],
            ['HR', 'appraisal', ['view', 'manage']],
            ['HR', 'contract', ['view', 'manage']],
            ['HR', 'termination', ['view', 'manage']],

            ['BI', 'view',         ['view']],
            ['BI', 'schedule',     ['view', 'manage', 'run', 'download']],

            // CC — Call Center (Fase 20)
            ['CC', 'call', ['view', 'log', 'manage']],

            // GIS — GIS (Fase 19)
            ['GIS', 'feature', ['view', 'create', 'update', 'delete']],

            // INT — Integration (Fase 22)
            ['INT', 'integration', ['view', 'manage']],
            ['INT', 'apikey', ['view', 'create', 'revoke']],
        ];

        foreach ($map as [$module, $resource, $actions]) {
            foreach ($actions as $action) {
                $code = strtolower($module).'.'.$resource.'.'.$action;
                Permission::updateOrCreate(
                    ['code' => $code],
                    [
                        'module_code' => $module,
                        'resource' => $resource,
                        'action' => $action,
                        'description' => ucfirst($action).' '.$resource.' ('.$module.')',
                    ]
                );
            }
        }
    }
}
