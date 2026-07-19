<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\DB;

/**
 * AccountingReportService — 5 laporan standar dari journal_entry_lines
 * (satu sumber data, PRD 3.4): Buku Besar, Neraca Saldo (Trial Balance),
 * Laba Rugi, Neraca, Arus Kas.
 */
class AccountingReportService
{
    /** Buku Besar: mutasi per akun dalam rentang periode. */
    public function generalLedger(string $fromPeriod, string $toPeriod): array
    {
        $accounts = ChartOfAccount::orderBy('code')->get();
        $result = [];

        foreach ($accounts as $account) {
            $lines = JournalEntryLine::where('account_id', $account->id)
                ->whereHas('entry', fn ($q) => $q->whereBetween('period', [$fromPeriod, $toPeriod]))
                ->with('entry:id,entry_date,description')
                ->get();

            if ($lines->isEmpty()) {
                continue;
            }

            $debit = (float) $lines->where('type', 'DEBIT')->sum('amount');
            $credit = (float) $lines->where('type', 'KREDIT')->sum('amount');

            $result[] = [
                'account_code' => $account->code,
                'account_name' => $account->name,
                'total_debit' => $debit,
                'total_credit' => $credit,
                'balance' => $account->normal_balance === 'DEBIT' ? $debit - $credit : $credit - $debit,
                'lines' => $lines->map(fn ($l) => [
                    'date' => $l->entry->entry_date,
                    'description' => $l->entry->description,
                    'type' => $l->type,
                    'amount' => (float) $l->amount,
                ])->values(),
            ];
        }

        return $result;
    }

    /** Neraca Saldo: total debit vs kredit per akun (harus balance). */
    public function trialBalance(string $fromPeriod, string $toPeriod): array
    {
        $rows = $this->balancesByAccount($fromPeriod, $toPeriod);

        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $data = [];

        foreach ($rows as $r) {
            $balance = $r['normal_balance'] === 'DEBIT'
                ? $r['debit'] - $r['credit']
                : $r['credit'] - $r['debit'];

            if (abs($balance) < 0.001) {
                continue;
            }

            $debitCol = 0.0;
            $creditCol = 0.0;
            if ($r['normal_balance'] === 'DEBIT') {
                $balance >= 0 ? $debitCol = $balance : $creditCol = -$balance;
            } else {
                $balance >= 0 ? $creditCol = $balance : $debitCol = -$balance;
            }

            $totalDebit += $debitCol;
            $totalCredit += $creditCol;
            $data[] = [
                'account_code' => $r['code'],
                'account_name' => $r['name'],
                'debit' => round($debitCol, 2),
                'credit' => round($creditCol, 2),
            ];
        }

        return [
            'rows' => $data,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'is_balanced' => abs($totalDebit - $totalCredit) < 0.01,
        ];
    }

    /** Laba Rugi: pendapatan - beban. */
    public function incomeStatement(string $fromPeriod, string $toPeriod): array
    {
        $rows = $this->balancesByAccount($fromPeriod, $toPeriod);

        $revenue = [];
        $expense = [];
        $totalRevenue = 0.0;
        $totalExpense = 0.0;

        foreach ($rows as $r) {
            if ($r['type'] === 'REVENUE') {
                $amt = $r['credit'] - $r['debit'];
                $totalRevenue += $amt;
                $revenue[] = ['account_code' => $r['code'], 'account_name' => $r['name'], 'amount' => round($amt, 2)];
            } elseif ($r['type'] === 'EXPENSE') {
                $amt = $r['debit'] - $r['credit'];
                $totalExpense += $amt;
                $expense[] = ['account_code' => $r['code'], 'account_name' => $r['name'], 'amount' => round($amt, 2)];
            }
        }

        return [
            'revenue' => $revenue,
            'expense' => $expense,
            'total_revenue' => round($totalRevenue, 2),
            'total_expense' => round($totalExpense, 2),
            'net_income' => round($totalRevenue - $totalExpense, 2),
        ];
    }

    /** Neraca: Aset = Kewajiban + Ekuitas (+ laba berjalan). */
    public function balanceSheet(string $fromPeriod, string $toPeriod): array
    {
        $rows = $this->balancesByAccount($fromPeriod, $toPeriod);

        $assets = [];
        $liabilities = [];
        $equity = [];
        $totalAsset = 0.0;
        $totalLiability = 0.0;
        $totalEquity = 0.0;

        foreach ($rows as $r) {
            if ($r['type'] === 'ASSET') {
                $amt = $r['debit'] - $r['credit'];
                $totalAsset += $amt;
                $assets[] = ['account_code' => $r['code'], 'account_name' => $r['name'], 'amount' => round($amt, 2)];
            } elseif ($r['type'] === 'LIABILITY') {
                $amt = $r['credit'] - $r['debit'];
                $totalLiability += $amt;
                $liabilities[] = ['account_code' => $r['code'], 'account_name' => $r['name'], 'amount' => round($amt, 2)];
            } elseif ($r['type'] === 'EQUITY') {
                $amt = $r['credit'] - $r['debit'];
                $totalEquity += $amt;
                $equity[] = ['account_code' => $r['code'], 'account_name' => $r['name'], 'amount' => round($amt, 2)];
            }
        }

        $netIncome = $this->incomeStatement($fromPeriod, $toPeriod)['net_income'];
        $totalEquityWithIncome = $totalEquity + $netIncome;

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'current_earnings' => $netIncome,
            'total_asset' => round($totalAsset, 2),
            'total_liability' => round($totalLiability, 2),
            'total_equity' => round($totalEquityWithIncome, 2),
            'is_balanced' => abs($totalAsset - ($totalLiability + $totalEquityWithIncome)) < 0.01,
        ];
    }

    /** Arus Kas (sederhana): mutasi akun Kas/Bank. */
    public function cashFlow(string $fromPeriod, string $toPeriod): array
    {
        $cash = ChartOfAccount::where('code', '1-001')->first();
        if (! $cash) {
            return ['inflow' => 0, 'outflow' => 0, 'net' => 0, 'lines' => []];
        }

        $lines = JournalEntryLine::where('account_id', $cash->id)
            ->whereHas('entry', fn ($q) => $q->whereBetween('period', [$fromPeriod, $toPeriod]))
            ->with('entry:id,entry_date,description')
            ->get();

        $inflow = (float) $lines->where('type', 'DEBIT')->sum('amount');
        $outflow = (float) $lines->where('type', 'KREDIT')->sum('amount');

        return [
            'inflow' => round($inflow, 2),
            'outflow' => round($outflow, 2),
            'net' => round($inflow - $outflow, 2),
            'lines' => $lines->map(fn ($l) => [
                'date' => $l->entry->entry_date,
                'description' => $l->entry->description,
                'direction' => $l->type === 'DEBIT' ? 'in' : 'out',
                'amount' => (float) $l->amount,
            ])->values(),
        ];
    }

    /** Helper: total debit/kredit per akun dalam rentang periode. */
    protected function balancesByAccount(string $fromPeriod, string $toPeriod): array
    {
        $accounts = ChartOfAccount::orderBy('code')->get();
        $out = [];

        foreach ($accounts as $account) {
            $agg = JournalEntryLine::where('account_id', $account->id)
                ->whereHas('entry', fn ($q) => $q->whereBetween('period', [$fromPeriod, $toPeriod]))
                ->select('type', DB::raw('SUM(amount) as total'))
                ->groupBy('type')
                ->pluck('total', 'type');

            $out[] = [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'normal_balance' => $account->normal_balance,
                'debit' => (float) ($agg['DEBIT'] ?? 0),
                'credit' => (float) ($agg['KREDIT'] ?? 0),
            ];
        }

        return $out;
    }
}
