<?php

namespace App\Services;

use App\Models\HrEmployee;
use App\Models\PayrollComponent;
use App\Support\TenantContext;

class PayrollService
{
    public function __construct(
        private JournalService $journal,
        private TaxService $tax,
    ) {}

    public function calculate(HrEmployee $employee, string $period): array
    {
        $grade = $employee->grade;
        $baseSalary = $grade ? ($grade->min_salary + $grade->max_salary) / 2 : 0;

        $components = PayrollComponent::where('pdam_org_id', $employee->pdam_org_id)
            ->orderBy('type')
            ->get();

        $earnings = [];
        $deductions = [];
        $totalEarnings = $baseSalary;
        $totalDeductions = 0;

        foreach ($components as $comp) {
            $amount = 0;
            if ($comp->default_amount) {
                $amount = (float) $comp->default_amount;
            } elseif ($comp->default_percent) {
                $amount = $baseSalary * ((float) $comp->default_percent / 100);
            }
            $amount = round($amount, 2);

            if ($comp->type === 'earning') {
                $earnings[] = ['code' => $comp->code, 'name' => $comp->name, 'amount' => $amount];
                $totalEarnings += $amount;
            } else {
                $deductions[] = ['code' => $comp->code, 'name' => $comp->name, 'amount' => $amount];
                $totalDeductions += $amount;
            }
        }

        // BPJS Kesehatan: 1% pegawai
        $bpjsKesPegawai = round($baseSalary * 0.01, 2);
        $deductions[] = ['code' => 'BPJS_KES', 'name' => 'BPJS Kesehatan (1%)', 'amount' => $bpjsKesPegawai];
        $totalDeductions += $bpjsKesPegawai;

        // BPJS TK - JHT: 2%
        $jht = round($baseSalary * 0.02, 2);
        $deductions[] = ['code' => 'BPJS_JHT', 'name' => 'BPJS TK - JHT (2%)', 'amount' => $jht];
        $totalDeductions += $jht;

        // BPJS TK - JP: 1%
        $jp = round($baseSalary * 0.01, 2);
        $deductions[] = ['code' => 'BPJS_JP', 'name' => 'BPJS TK - JP (1%)', 'amount' => $jp];
        $totalDeductions += $jp;

        // PPh 21
        $ptkp = $this->tax->calculatePph21($baseSalary, $employee->tax_status ?? 'TK', $employee->dependents ?? 0);
        $deductions[] = ['code' => 'PPH21', 'name' => 'PPh 21', 'amount' => $ptkp['tax_monthly']];
        $totalDeductions += $ptkp['tax_monthly'];

        $takeHomePay = $totalEarnings - $totalDeductions;

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'nip' => $employee->nip,
            'period' => $period,
            'base_salary' => round($baseSalary, 2),
            'earnings' => $earnings,
            'total_earnings' => round($totalEarnings, 2),
            'deductions' => $deductions,
            'total_deductions' => round($totalDeductions, 2),
            'take_home_pay' => round($takeHomePay, 2),
            'tax_detail' => $ptkp,
        ];
    }

    public function runBatch(array $employeeIds, string $period): array
    {
        $results = [];
        $orgId = TenantContext::id();

        foreach ($employeeIds as $empId) {
            $employee = HrEmployee::where('pdam_org_id', $orgId)->find($empId);
            if (! $employee || $employee->status !== 'active') {
                continue;
            }

            $results[] = $this->calculate($employee, $period);
        }

        $totalNetPay = array_sum(array_column($results, 'take_home_pay'));

        return [
            'period' => $period,
            'employee_count' => count($results),
            'total_net_pay' => $totalNetPay,
            'details' => $results,
        ];
    }

    public function postJournal(array $payrollResults, string $period): void
    {
        $totalSalary = 0;
        $totalBpjsKes = 0;
        $totalBpjsTk = 0;
        $totalPph21 = 0;

        foreach ($payrollResults['details'] as $r) {
            $totalSalary += $r['total_earnings'];
            foreach ($r['deductions'] as $d) {
                match ($d['code']) {
                    'BPJS_KES' => $totalBpjsKes += $d['amount'],
                    'BPJS_JHT', 'BPJS_JP' => $totalBpjsTk += $d['amount'],
                    'PPH21' => $totalPph21 += $d['amount'],
                    default => null,
                };
            }
        }

        $totalNet = $payrollResults['total_net_pay'];

        $this->journal->record("Payroll {$period}", [
            ['account_code' => '5-101', 'type' => 'DEBIT', 'amount' => $totalSalary, 'memo' => 'Beban gaji & tunjangan'],
            ['account_code' => '2-004', 'type' => 'KREDIT', 'amount' => $totalBpjsKes + $totalBpjsTk + $totalPph21, 'memo' => 'Utang BPJS & PPh21'],
            ['account_code' => '2-001', 'type' => 'KREDIT', 'amount' => $totalNet, 'memo' => 'Utang gaji bersih'],
        ], 'payroll', 0, $period.'-01');
    }
}
