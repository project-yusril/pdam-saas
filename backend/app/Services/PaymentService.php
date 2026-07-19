<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\CustomerProspect;
use App\Models\InstallmentSchedule;
use App\Models\Payment;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * PaymentService — buat & konfirmasi pembayaran + auto-jurnal.
 * Mendukung idempotency: transaksi gateway yang sama tidak dicatat dua kali.
 */
class PaymentService
{
    public function __construct(private JournalService $journal) {}

    /** Buat pembayaran tagihan bulanan (pending). */
    public function createForBill(Bill $bill, string $channel = 'gateway', ?string $method = null): Payment
    {
        if ($bill->status === 'paid') {
            throw new RuntimeException('Tagihan sudah lunas.');
        }

        return Payment::create([
            'payment_type' => 'monthly_bill',
            'bill_id' => $bill->id,
            'customer_id' => $bill->customer_id,
            'payment_number' => $this->generateNumber(),
            'amount' => $bill->amount_due,
            'payment_method' => $method,
            'channel' => $channel,
            'midtrans_order_id' => 'ORD-'.uniqid(),
            'status' => 'pending',
        ]);
    }

    /**
     * Buat pembayaran biaya pemasangan (installation_fee) untuk calon pelanggan.
     * Prospek harus dalam status payment_pending & punya nominal biaya.
     */
    public function createForInstallation(CustomerProspect $prospect, string $channel = 'gateway', ?string $method = null): Payment
    {
        if ($prospect->status !== 'payment_pending') {
            throw new RuntimeException('Prospek tidak menunggu pembayaran pemasangan.');
        }
        if (! $prospect->installation_fee || (float) $prospect->installation_fee <= 0) {
            throw new RuntimeException('Biaya pemasangan belum ditetapkan.');
        }

        return Payment::create([
            'payment_type' => 'installation_fee',
            'prospect_id' => $prospect->id,
            'payment_number' => $this->generateNumber(),
            'amount' => $prospect->installation_fee,
            'payment_method' => $method,
            'channel' => $channel,
            'midtrans_order_id' => 'ORD-'.uniqid(),
            'status' => 'pending',
            'expired_at' => $prospect->payment_due_at,
        ]);
    }

    public function createForInstallment(InstallmentSchedule $schedule, string $channel = 'gateway', ?string $method = null): Payment
    {
        if ($schedule->status === 'paid') {
            throw new RuntimeException('Termin cicilan sudah lunas.');
        }

        return Payment::create([
            'payment_type' => 'installment',
            'installment_schedule_id' => $schedule->id,
            'customer_id' => $schedule->plan->customer_id ?? null,
            'payment_number' => $this->generateNumber(),
            'amount' => $schedule->amount,
            'payment_method' => $method,
            'channel' => $channel,
            'midtrans_order_id' => 'ORD-'.uniqid(),
            'status' => 'pending',
        ]);
    }

    /**
     * Tandai pembayaran sukses + auto-jurnal (Kas ↔ Piutang) + tandai bill paid.
     * Idempotent: jika sudah success, tidak diproses ulang.
     */
    public function markPaid(Payment $payment, ?string $transactionId = null, ?string $method = null): Payment
    {
        if ($payment->status === 'success') {
            return $payment; // idempotent guard
        }

        return DB::transaction(function () use ($payment, $transactionId, $method) {
            $payment->update([
                'status' => 'success',
                'paid_at' => now(),
                'midtrans_transaction_id' => $transactionId ?? $payment->midtrans_transaction_id,
                'payment_method' => $method ?? $payment->payment_method,
            ]);

            if ($payment->payment_type === 'monthly_bill' && $payment->bill_id) {
                $bill = Bill::find($payment->bill_id);
                if ($bill && $bill->status !== 'paid') {
                    $entry = $this->journal->record(
                        "Pembayaran tagihan {$bill->bill_number}",
                        [
                            ['account_code' => '1-001', 'type' => 'DEBIT', 'amount' => $bill->amount_due, 'memo' => 'Kas/Bank'],
                            ['account_code' => '1-002', 'type' => 'KREDIT', 'amount' => $bill->amount_due, 'memo' => 'Pelunasan piutang'],
                        ],
                        'PAYMENT',
                        $payment->id,
                    );
                    $bill->update(['status' => 'paid']);
                    $payment->update(['journal_entry_id' => $entry->id]);
                }
            }

            if ($payment->payment_type === 'installation_fee' && $payment->prospect_id) {
                $entry = $this->journal->record(
                    "Biaya pemasangan - prospek #{$payment->prospect_id}",
                    [
                        ['account_code' => '1-001', 'type' => 'DEBIT', 'amount' => $payment->amount, 'memo' => 'Kas/Bank'],
                        ['account_code' => '4-002', 'type' => 'KREDIT', 'amount' => $payment->amount, 'memo' => 'Pendapatan pemasangan'],
                    ],
                    'INSTALLATION_FEE',
                    $payment->id,
                );
                $payment->update(['journal_entry_id' => $entry->id]);

                // Prospek lunas → siap dijadwalkan pemasangan (PRD 3.1 tahap 5→6)
                $prospect = CustomerProspect::find($payment->prospect_id);
                if ($prospect && $prospect->status === 'payment_pending') {
                    $prospect->update(['status' => 'payment_paid']);
                }
            }

            return $payment->fresh();

        });
    }

    protected function generateNumber(): string
    {
        $org = TenantContext::id();

        return sprintf('PAY-%s-%s', $org, strtoupper(uniqid()));
    }
}
