<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentRefund;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class RefundException extends RuntimeException {}

/**
 * RefundService — pengembalian pembayaran + jurnal PEMBERALIKAN (DEBIT↔KREDIT
 * tukar dari journal asalnya, di-scale utk partial). Full refund menandai
 * Payment berstatus `refunded`. Semua ini gated `business.refund.enabled`
 * (PRD §23 keputusan bisnis); tanpa itu service menolak (endpoint UI audit).
 */
class RefundService
{
    public function __construct(private JournalService $journal) {}

    public function assertEnabled(): void
    {
        if (! config('business.refund.enabled')) {
            throw new RefundException('Fitur pengembalian dana dinonaktifkan pada instalasi ini.');
        }
    }

    /**
     * Sisa nominal pembayaran yang belum dikembalikan.
     */
    public function remaining(Payment $payment): float
    {
        return round((float) $payment->amount - (float) PaymentRefund::where('payment_id', $payment->id)->sum('amount'), 2);
    }

    /**
     * Eksekusi satu refund (partial/full) terhadap Payment success.
     */
    public function refund(Payment $payment, int $approvedBy, ?float $amount = null, string $reason = '', string $method = 'counter', bool $reversesGateway = false): PaymentRefund
    {
        $this->assertEnabled();

        if ($payment->status !== 'success') {
            throw new RefundException('Hanya pembayaran success yang dapat dikembalikan.');
        }
        if (! $payment->journal_entry_id) {
            throw new RefundException('Pembayaran tidak punya jurnal pembayaran untuk dibalik (belum diposting).');
        }

        $remaining = $this->remaining($payment);
        $amount = $amount !== null ? round($amount, 2) : $remaining;
        if ($amount <= 0) {
            throw new InvalidArgumentException('Jumlah refund harus > 0.');
        }
        if ($amount > $remaining) {
            throw new RefundException("Refund {$amount} melebihi sisa bisa dikembalikan {$remaining}.");
        }

        if ($reversesGateway && $payment->channel === 'gateway' && $payment->midtrans_transaction_id
            && config('business.refund.auto_gateway')) {
            // gateway tidak dipanggil di v1 — flag hanya menandai sudah diproses manual.
        }

        return DB::transaction(function () use ($payment, $approvedBy, $amount, $reason, $method, $reversesGateway) {
            $refund = PaymentRefund::create([
                'payment_id' => $payment->id,
                'customer_id' => $payment->customer_id,
                'amount' => $amount,
                'approved_by' => $approvedBy,
                'method' => $method,
                'reverses_gateway' => $reversesGateway,
                'reason' => $reason ?: null,
                'status' => 'completed',
            ]);

            $factor = $amount / (float) $payment->amount;
            $journal = $this->journal->record(
                "Pengembalian {$payment->payment_number} (#{$refund->id})",
                $this->reverseLines($payment, $factor, $amount),
                'payment_refund',
                $refund->id,
            );
            $refund->update(['journal_entry_id' => $journal->id]);

            $sumRefunded = (float) PaymentRefund::where('payment_id', $payment->id)->sum('amount');
            if ($sumRefunded >= (float) $payment->amount - 0.005) {
                $payment->update(['status' => 'refunded']);
            }

            return $refund->fresh();
        });
    }

    /**
     * Tukar DEBIT/KREDIT dari journal asal, di-scale proporsional ke amount refund.
     *
     * @return array<int,array{account_id:string,type:string,amount:float,memo:string}>
     */
    protected function reverseLines(Payment $payment, float $factor, float $amount): array
    {
        $orig = JournalEntry::with('lines')->find($payment->journal_entry_id);
        if (! $orig || $orig->lines->count() < 2) {
            throw new RefundException('Jurnal pembayaran asli tidak punya dua kaki untuk dibalik.');
        }

        $out = $orig->lines->map(fn ($line) => [
            'account_id' => $line->account_id,
            'type' => $line->type === 'DEBIT' ? 'KREDIT' : 'DEBIT',
            'amount' => round((float) $line->amount * $factor, 2),
            'memo' => 'pembalikan '.($line->type === 'DEBIT' ? 'kas/bank' : 'piutang/pendapatan'),
        ])->values()->all();

        // koreksi pembulatan ke satu kaki terbesar agar D=K tepat
        $debit = round(array_sum(array_map(fn ($l) => $l['type'] === 'DEBIT' ? $l['amount'] : 0, $out)), 2);
        $credit = round(array_sum(array_map(fn ($l) => $l['type'] === 'KREDIT' ? $l['amount'] : 0, $out)), 2);
        $diff = round($credit - $debit, 2);
        if (abs($diff) > 0.005) {
            $max = null;
            foreach ($out as $i => $l) {
                if ($l['type'] === 'DEBIT' && ($max === null || $out[$max]['amount'] < $l['amount'])) {
                    $max = $i;
                }
            }
            if ($max !== null) {
                $out[$max]['amount'] = round($out[$max]['amount'] + $diff, 2);
            }
        }

        return $out;
    }
}
