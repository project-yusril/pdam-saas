<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SaasPurchaseOrder;
use App\Services\PaymentService;
use App\Services\SaasPurchaseService;
use App\Support\ApiResponse;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PaymentWebhookController — terima notifikasi Midtrans (PRD 3.2.5).
 *
 * Endpoint publik (tanpa auth Sanctum) tapi diverifikasi via signature_key:
 *   sha512(order_id + status_code + gross_amount + server_key).
 * Idempoten: PaymentService.markPaid tidak memproses ulang pembayaran sukses,
 * jadi webhook duplikat aman.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(private PaymentService $payments, private SaasPurchaseService $saasPurchases) {}

    public function handle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'string'],
            'status_code' => ['required', 'string'],
            'gross_amount' => ['required', 'string'],
            'signature_key' => ['required', 'string'],
            'transaction_status' => ['required', 'string'],
            'transaction_id' => ['nullable', 'string'],
            'payment_type' => ['nullable', 'string'],
        ]);

        $saasOrder = SaasPurchaseOrder::query()->withoutGlobalScopes()->where('order_number', $data['order_id'])->first();
        if ($saasOrder) {
            $serverKey = config('services.midtrans.server_key', '');
            $expected = hash('sha512', $data['order_id'].$data['status_code'].$data['gross_amount'].$serverKey);
            if (! hash_equals($expected, $data['signature_key'])) {
                return ApiResponse::error('INVALID_SIGNATURE', 'Signature tidak valid.', null, 403);
            }
            if (round((float) $saasOrder->amount, 2) !== round((float) $data['gross_amount'], 2)) {
                return ApiResponse::error('AMOUNT_MISMATCH', 'Nominal pembayaran tidak sesuai.', null, 422);
            }
            if (in_array($data['transaction_status'], ['capture', 'settlement'], true)) {
                $this->saasPurchases->settle($data['order_id'], $data['transaction_id'] ?? $data['order_id']);
            } elseif (in_array($data['transaction_status'], ['expire', 'cancel', 'deny'], true) && $saasOrder->status === 'pending') {
                $saasOrder->update(['status' => 'expired']);
            }

            return ApiResponse::message('Webhook diproses.');
        }

        // Cari payment lintas tenant berdasarkan order_id (unik global)
        $payment = Payment::withoutGlobalScope('tenant')
            ->where('midtrans_order_id', $data['order_id'])
            ->first();

        if (! $payment) {
            return ApiResponse::error('NOT_FOUND', 'Order tidak dikenal.', null, 404);
        }

        // Verifikasi signature terhadap server key tenant/global
        $serverKey = config('services.midtrans.server_key', '');
        $expected = hash('sha512', $data['order_id'].$data['status_code'].$data['gross_amount'].$serverKey);
        if (! hash_equals($expected, $data['signature_key'])) {
            return ApiResponse::error('INVALID_SIGNATURE', 'Signature tidak valid.', null, 403);
        }

        // Set konteks tenant agar jurnal ter-scope benar
        TenantContext::set($payment->pdam_org_id);

        $settled = in_array($data['transaction_status'], ['capture', 'settlement'], true);
        if ($settled) {
            $this->payments->markPaid(
                $payment,
                transactionId: $data['transaction_id'] ?? null,
                method: $data['payment_type'] ?? null,
            );
        } elseif (in_array($data['transaction_status'], ['expire', 'cancel', 'deny'], true)) {
            if ($payment->status !== 'success') {
                $payment->update(['status' => 'expired']);
            }
        }

        TenantContext::clear();

        return ApiResponse::message('Webhook diproses.');
    }
}
