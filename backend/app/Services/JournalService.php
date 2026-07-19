<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * JournalService — mesin jurnal double-entry (PRD 3.4 & Fase 1.6).
 *
 * Aturan mutlak: SUM(DEBIT) HARUS = SUM(KREDIT) per jurnal. Jika tidak,
 * transaksi ditolak (throw) sehingga tidak pernah ada jurnal tak seimbang.
 * Juga menolak posting ke periode akuntansi yang sudah closed (locked).
 */
class JournalService
{
    /** Toleransi selisih pembulatan (Rp). */
    private const EPSILON = 0.001;

    /**
     * Buat jurnal seimbang.
     *
     * @param  array<int, array{account_code?:string, account_id?:int, type:string, amount:float|int, memo?:string}>  $lines
     */
    public function record(
        string $description,
        array $lines,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $entryDate = null,
        string $createdBy = 'system'
    ): JournalEntry {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('Jurnal minimal 2 baris (double-entry).');
        }

        $date = $entryDate ?? now()->toDateString();
        $period = substr($date, 0, 7); // YYYY-MM

        $this->assertPeriodOpen($period);

        $debit = 0.0;
        $credit = 0.0;
        $resolved = [];

        foreach ($lines as $i => $line) {
            $type = strtoupper($line['type'] ?? '');
            if (! in_array($type, ['DEBIT', 'KREDIT'], true)) {
                throw new InvalidArgumentException("Baris #{$i}: type harus DEBIT atau KREDIT.");
            }

            $amount = round((float) ($line['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw new InvalidArgumentException("Baris #{$i}: amount harus > 0.");
            }

            $accountId = $line['account_id'] ?? $this->accountIdByCode($line['account_code'] ?? '');

            $resolved[] = [
                'account_id' => $accountId,
                'type' => $type,
                'amount' => $amount,
                'memo' => $line['memo'] ?? null,
            ];

            $type === 'DEBIT' ? $debit += $amount : $credit += $amount;
        }

        // Gerbang keseimbangan — inti double-entry
        if (abs($debit - $credit) > self::EPSILON) {
            throw new RuntimeException(
                "Jurnal tidak balance: DEBIT {$debit} != KREDIT {$credit}. Transaksi ditolak."
            );
        }

        return DB::transaction(function () use ($description, $date, $period, $referenceType, $referenceId, $createdBy, $resolved) {
            $entry = JournalEntry::create([
                'entry_number' => $this->generateEntryNumber($period),
                'entry_date' => $date,
                'period' => $period,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $createdBy,
            ]);

            foreach ($resolved as $line) {
                $entry->lines()->create([
                    'pdam_org_id' => $entry->pdam_org_id,
                    'account_id' => $line['account_id'],
                    'type' => $line['type'],
                    'amount' => $line['amount'],
                    'memo' => $line['memo'],
                ]);
            }

            return $entry->load('lines');
        });
    }

    /** Tolak posting ke periode yang sudah ditutup. */
    protected function assertPeriodOpen(string $period): void
    {
        $ap = AccountingPeriod::where('period', $period)->first();
        if ($ap && $ap->isClosed()) {
            throw new RuntimeException("Periode {$period} sudah ditutup. Jurnal tidak boleh diposting.");
        }
    }

    protected function accountIdByCode(string $code): int
    {
        $account = ChartOfAccount::where('code', $code)->first();
        if (! $account) {
            throw new InvalidArgumentException("Akun COA '{$code}' tidak ditemukan.");
        }

        return $account->id;
    }

    protected function generateEntryNumber(string $period): string
    {
        $org = TenantContext::id();
        $count = JournalEntry::where('period', $period)->count() + 1;

        return sprintf('JE-%s-%s-%04d', $org, str_replace('-', '', $period), $count);
    }
}
