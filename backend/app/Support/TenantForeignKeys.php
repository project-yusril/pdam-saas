<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class TenantForeignKeys
{
    public const TABLES = [
        'accounting_periods', 'activity_logs', 'api_keys', 'app_notifications', 'attendances',
        'bill_items', 'billing_settings', 'bills', 'call_logs', 'certifications',
        'chart_of_accounts', 'chemical_forecasts', 'chemical_purchase_requests', 'chemical_qc_tests',
        'chemical_receipt_items', 'chemical_receipts', 'chemical_stock_opnames', 'chemical_stocks',
        'chemical_suppliers', 'chemical_transactions', 'chemical_usages', 'chemicals',
        'customer_prospects', 'customer_status_history', 'customers', 'disconnections',
        'distribution_readings', 'dma_zones', 'documents', 'employee_contracts', 'employee_grades',
        'employment_terminations', 'gis_features', 'hr_employees', 'installation_schedules',
        'installment_plan_bills', 'installment_plans', 'installment_schedules', 'integration_logs',
        'integrations', 'job_positions', 'journal_entries', 'journal_entry_lines', 'leave_types',
        'leaves', 'maintenance_records', 'maintenance_schedules', 'material_stocks',
        'material_transactions', 'materials', 'meter_anomalies', 'meter_lifecycle_events',
        'meter_readings', 'meter_replacements', 'meter_route_assignments', 'meter_route_streets',
        'meter_routes', 'meter_stock', 'meters', 'ml_predictions', 'nrw_balances',
        'organization_units', 'overtime_requests', 'ownership_transfers', 'payment_gateway_logs',
        'payments', 'payroll_components', 'performance_appraisals', 'privacy_audit_events',
        'privacy_purge_requests', 'production_logs', 'purchase_orders', 'purchase_requests',
        'reading_periods', 'reconnections', 'repair_orders', 'sensor_readings', 'shift_schedules',
        'shifts', 'stock_adjustments', 'stock_transfer_items', 'stock_transfers', 'streets',
        'suppliers', 'survey_reports', 'tariff_categories', 'tariff_tiers', 'tender_bids',
        'tenders', 'trainings', 'vendor_contracts', 'vendor_evaluations', 'vendors', 'warehouses',
        'work_order_logs', 'work_orders', 'zones',
    ];

    public const ACTORS = [
        ['meter_route_assignments', 'officer_id', 'afk_01', 'aix_01'],
        ['reading_periods', 'opened_by', 'afk_02', 'aix_02'],
        ['reading_periods', 'closed_by', 'afk_03', 'aix_03'],
        ['meter_readings', 'read_by', 'afk_04', 'aix_04'],
        ['meter_readings', 'verified_by', 'afk_05', 'aix_05'],
        ['meter_replacements', 'processed_by', 'afk_06', 'aix_06'],
        ['meter_lifecycle_events', 'performed_by', 'afk_07', 'aix_07'],
        ['meter_anomalies', 'reviewed_by', 'afk_08', 'aix_08'],
    ];

    public static function assertClean(): void
    {
        $issues = [];

        foreach (self::TABLES as $table) {
            $ids = DB::table($table.' as child')
                ->leftJoin('pdam_organizations as tenant', 'tenant.id', '=', 'child.pdam_org_id')
                ->whereNotNull('child.pdam_org_id')
                ->whereNull('tenant.id')
                ->orderBy('child.id')
                ->limit(20)
                ->pluck('child.id')
                ->all();
            if ($ids !== []) {
                $issues[] = $table.'.pdam_org_id orphan rows ['.implode(',', $ids).']';
            }
        }

        foreach (self::ACTORS as [$table, $column]) {
            $rows = DB::table($table.' as child')
                ->leftJoin('users as actor', 'actor.id', '=', 'child.'.$column)
                ->whereNotNull('child.'.$column)
                ->where(function ($query) {
                    $query->whereNull('actor.id')
                        ->orWhereColumn('actor.pdam_org_id', '<>', 'child.pdam_org_id')
                        ->orWhereNull('actor.pdam_org_id');
                })
                ->orderBy('child.id')
                ->limit(20)
                ->get(['child.id', 'child.pdam_org_id', 'child.'.$column, 'actor.pdam_org_id as actor_pdam_org_id']);
            if ($rows->isNotEmpty()) {
                $details = $rows->map(fn (object $row): string => sprintf(
                    '%s(child=%s,actor=%s)',
                    $row->id,
                    $row->pdam_org_id ?? 'null',
                    $row->actor_pdam_org_id ?? 'missing/null',
                ))->implode(',');
                $issues[] = $table.'.'.$column.' invalid actors ['.$details.']';
            }
        }

        if ($issues !== []) {
            throw new RuntimeException("Tenant FK preflight failed; no business data was changed:\n - ".implode("\n - ", $issues));
        }
    }

    public static function addTenantBatch(int $offset, int $length): void
    {
        self::assertClean();

        foreach (array_slice(self::TABLES, $offset, $length, true) as $number => $tableName) {
            if (self::hasForeignKey($tableName, ['pdam_org_id'], 'pdam_organizations')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($number): void {
                $table->foreign('pdam_org_id', 'tfk_'.str_pad((string) ($number + 1), 3, '0', STR_PAD_LEFT))
                    ->references('id')->on('pdam_organizations')->restrictOnDelete();
            });
        }
    }

    public static function dropTenantBatch(int $offset, int $length): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        foreach (array_reverse(array_slice(self::TABLES, $offset, $length, true), true) as $number => $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($number): void {
                $table->dropForeign('tfk_'.str_pad((string) ($number + 1), 3, '0', STR_PAD_LEFT));
            });
        }
    }

    public static function hasForeignKey(string $table, array $columns, string $foreignTable): bool
    {
        return collect(Schema::getForeignKeys($table))->contains(
            fn (array $key): bool => $key['columns'] === $columns && $key['foreign_table'] === $foreignTable,
        );
    }
}
