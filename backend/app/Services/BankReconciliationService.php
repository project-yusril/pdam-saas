<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankReconciliation;

class BankReconciliationService
{
    public function reconcile(BankAccount $account, float $statementBalance, string $statementDate, ?string $notes = null): BankReconciliation
    {
        $bookBalance = (float) $account->current_balance;
        $difference = round($statementBalance - $bookBalance, 2);

        return BankReconciliation::create([
            'pdam_org_id' => $account->pdam_org_id,
            'bank_account_id' => $account->id,
            'statement_date' => $statementDate,
            'reconciliation_date' => now()->toDateString(),
            'statement_balance' => $statementBalance,
            'book_balance' => $bookBalance,
            'difference' => $difference,
            'status' => abs($difference) < 0.01 ? 'balanced' : 'unbalanced',
            'notes' => $notes,
        ]);
    }

    public function transfer(BankAccount $from, BankAccount $to, float $amount, string $notes = ''): array
    {
        $from->decrement('current_balance', $amount);
        $to->increment('current_balance', $amount);

        $journal = app(JournalService::class);

        $entry = $journal->record('Transfer antar kas', [
            ['account_code' => $to->coa_account_code ?? '1-001', 'type' => 'DEBIT', 'amount' => $amount, 'memo' => "Transfer masuk - {$from->bank_name}"],
            ['account_code' => $from->coa_account_code ?? '1-001', 'type' => 'KREDIT', 'amount' => $amount, 'memo' => "Transfer keluar - {$to->bank_name}"],
        ], 'bank_transfer', 0);

        return [
            'from' => $from->fresh(),
            'to' => $to->fresh(),
            'amount' => $amount,
            'journal_entry_id' => $entry->id,
        ];
    }

    public function getReconciliationSummary(BankAccount $account): array
    {
        $last = BankReconciliation::where('bank_account_id', $account->id)->latest('reconciliation_date')->first();

        return [
            'bank_account' => $account->only(['id', 'code', 'account_name', 'account_number', 'bank_name', 'current_balance']),
            'last_reconciliation' => $last ? [
                'date' => $last->reconciliation_date->toDateString(),
                'statement_balance' => (float) $last->statement_balance,
                'book_balance' => (float) $last->book_balance,
                'difference' => (float) $last->difference,
                'status' => $last->status,
            ] : null,
        ];
    }
}
