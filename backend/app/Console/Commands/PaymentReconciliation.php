<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\PdamOrganization;
use App\Services\Gateways\MidtransSnap;
use App\Services\PaymentService;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class PaymentReconciliation extends Command
{
    protected $signature = 'pdam:payment-reconciliation';

    protected $description = 'Rekonsiliasi pembayaran (cocokkan Midtrans vs DB)';

    public function handle(MidtransSnap $midtrans, PaymentService $payments): int
    {
        $organizations = PdamOrganization::all();

        foreach ($organizations as $org) {
            TenantContext::set($org->id);

            $pendingPayments = Payment::where('channel', 'gateway')
                ->where('status', 'pending')
                ->where('created_at', '>', now()->subDays(7))
                ->get();

            foreach ($pendingPayments as $payment) {
                try {
                    $status = $midtrans->getStatus($payment->midtrans_order_id);
                    $txStatus = $status['transaction_status'] ?? null;

                    if (in_array($txStatus, ['capture', 'settlement'])) {
                        $payments->markPaid($payment, $status['transaction_id'] ?? null, $status['payment_type'] ?? null);
                        $this->info("Reconciled: {$payment->midtrans_order_id}");
                    } elseif (in_array($txStatus, ['expire', 'cancel', 'deny', 'failure'])) {
                        $payment->update(['status' => 'expired']);
                        $this->info("Expired: {$payment->midtrans_order_id}");
                    }
                } catch (\Exception $e) {
                    $this->warn("Check failed for {$payment->midtrans_order_id}: {$e->getMessage()}");
                }
            }

            TenantContext::clear();
        }

        $this->info('Rekonsiliasi selesai.');

        return self::SUCCESS;
    }
}
