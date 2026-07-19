<?php

namespace App\Support;

final class ReportDatasetRegistry
{
    private const DATASETS = [
        'bills' => [
            'table' => 'bills',
            'module' => null,
            'permission' => 'core.bill.export',
            'export_columns' => ['bill_number', 'period', 'consumption', 'water_charge', 'abonemen', 'meter_maintenance_fee', 'admin_fee', 'penalty', 'amount_due', 'status', 'due_date', 'created_at'],
            'export_filters' => ['period', 'status', 'due_date'],
            'bi_filters' => ['period', 'status', 'due_date'],
            'dimensions' => ['period', 'status', 'due_date'],
            'metrics' => [
                'id' => ['count'],
                'consumption' => ['sum', 'avg', 'min', 'max'],
                'water_charge' => ['sum', 'avg', 'min', 'max'],
                'amount_due' => ['sum', 'avg', 'min', 'max'],
                'penalty' => ['sum', 'avg', 'min', 'max'],
            ],
            'export_default' => ['bill_number', 'period', 'consumption', 'amount_due', 'status', 'due_date'],
        ],
        'customers' => [
            'table' => 'customers',
            'module' => null,
            'permission' => 'core.customer.view',
            'export_columns' => ['customer_number', 'status', 'installation_date', 'created_at'],
            'export_filters' => ['status', 'installation_date'],
            'bi_filters' => ['zone_id', 'tariff_category_id', 'status', 'installation_date'],
            'dimensions' => ['zone_id', 'tariff_category_id', 'status', 'installation_date'],
            'metrics' => ['id' => ['count']],
            'export_default' => ['customer_number', 'status', 'installation_date'],
        ],
        'payments' => [
            'table' => 'payments',
            'module' => null,
            'permission' => 'core.payment.view',
            'export_columns' => ['payment_number', 'payment_type', 'amount', 'payment_method', 'channel', 'status', 'paid_at', 'created_at'],
            'export_filters' => ['payment_type', 'payment_method', 'channel', 'status', 'paid_at'],
            'bi_filters' => ['payment_type', 'payment_method', 'channel', 'status', 'paid_at'],
            'dimensions' => ['payment_type', 'payment_method', 'channel', 'status', 'paid_at'],
            'metrics' => ['id' => ['count'], 'amount' => ['sum', 'avg', 'min', 'max']],
            'export_default' => ['payment_number', 'payment_type', 'amount', 'payment_method', 'status', 'paid_at'],
        ],
        'complaints' => [
            'table' => 'complaints',
            'module' => 'CRM',
            'permission' => 'crm.complaint.view',
            'export_columns' => ['ticket_number', 'category', 'priority', 'status', 'sla_due_at', 'resolved_at', 'created_at'],
            'export_filters' => ['category', 'priority', 'status', 'sla_due_at', 'resolved_at'],
            'bi_filters' => ['category', 'priority', 'status', 'sla_due_at', 'resolved_at'],
            'dimensions' => ['category', 'priority', 'status'],
            'metrics' => ['id' => ['count']],
            'export_default' => ['ticket_number', 'category', 'priority', 'status', 'sla_due_at'],
        ],
        'employees' => [
            'table' => 'hr_employees',
            'module' => 'HR',
            'permission' => 'hr.employee.view',
            'export_columns' => ['employment_status', 'join_date', 'resign_date', 'status', 'created_at'],
            'export_filters' => ['employment_status', 'join_date', 'status'],
            'bi_filters' => ['zone_id', 'position_id', 'unit_id', 'grade_id', 'employment_status', 'join_date', 'status'],
            'dimensions' => ['zone_id', 'position_id', 'unit_id', 'grade_id', 'employment_status', 'status'],
            'metrics' => ['id' => ['count']],
            'export_default' => ['employment_status', 'join_date', 'status'],
        ],
        'work_orders' => [
            'table' => 'work_orders',
            'module' => 'FSM',
            'permission' => 'fsm.wo.view',
            'export_columns' => ['wo_number', 'type', 'priority', 'status', 'sla_due_at', 'started_at', 'completed_at', 'created_at'],
            'export_filters' => ['type', 'priority', 'status', 'sla_due_at'],
            'bi_filters' => ['type', 'priority', 'zone_id', 'status', 'sla_due_at'],
            'dimensions' => ['type', 'priority', 'zone_id', 'status'],
            'metrics' => ['id' => ['count']],
            'export_default' => ['wo_number', 'type', 'priority', 'status', 'sla_due_at', 'completed_at'],
        ],
        'zones' => [
            'table' => 'zones',
            'module' => 'ZONE',
            'permission' => 'zone.zone.view',
            'export_columns' => ['code', 'name', 'is_main', 'is_active', 'created_at'],
            'export_filters' => ['is_main', 'is_active'],
            'bi_filters' => ['is_main', 'is_active'],
            'dimensions' => ['is_main', 'is_active'],
            'metrics' => ['id' => ['count']],
            'export_default' => ['code', 'name', 'is_main', 'is_active'],
        ],
        'prospects' => [
            'table' => 'customer_prospects',
            'module' => 'SRV',
            'permission' => 'srv.prospect.view',
            'export_columns' => ['registration_number', 'status', 'installation_fee', 'payment_due_at', 'created_at'],
            'export_filters' => ['status', 'payment_due_at'],
            'bi_filters' => ['zone_id', 'status', 'payment_due_at'],
            'dimensions' => ['zone_id', 'status'],
            'metrics' => ['id' => ['count'], 'installation_fee' => ['sum', 'avg', 'min', 'max']],
            'export_default' => ['registration_number', 'status', 'installation_fee', 'payment_due_at'],
        ],
    ];

    public static function get(string $dataset): ?array
    {
        return self::DATASETS[$dataset] ?? null;
    }

    public static function names(): array
    {
        return array_keys(self::DATASETS);
    }
}
