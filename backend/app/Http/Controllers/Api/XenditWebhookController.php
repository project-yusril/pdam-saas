<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Gateways\XenditCharge;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * XenditWebhookController — callback provider kedua (PRD §23 "payment selain
 * Midtrans"). Verifikasi token callback (shared secret), idempoten terhadap
 * status `settlement/paid`, dan memakai PaymentService::markPaid seperti Midtrans.
 *
 * Payload Xendit (payment_intent): {id,status,event,...} + token via header
 * `x-callback-token`.
 */
class XenditWebhookController extends Controller
{
    public function __construct(
        private XenditCharge $xendit,
        private PaymentService $payments,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'id' => ['required', 'string'],
            'status' => ['required', 'string'],
            'event' => ['nullable', 'string'],
            'reference_id' => ['nullable', 'string'],
        ]);

        if (! $this->xendit->checkSignature(['callback_token' => $request->header('x-callback-token')])) {
            return ApiResponse::error('INVALID_CALLBACK_TOKEN', 'Token callback Xendit tidak valid.', null, 403);
        }

        $payment = Payment::withoutGlobalScope('tenant')
            ->where('midtrans_transaction_id', $payload['id'])
            ->orWhere('midtrans_order_id', $payload['id'])
            ->orWhere('midtrans_order_id', $payload['reference_id'] ?? '')
            ->first();

        if (! $payment) {
            return ApiResponse::error('NOT_FOUND', 'Pembayaran tidak dikenal.', null, 404);
        }

        TenantContext::set($payment->pdam_org_id);
        try {
            $status = strtolower($payload['status']);
            $paid = in_array($status, [
                'succeeded', 'paid', 'captured', 'settlement', 'completed',
            ], true);

            if ($paid) {
                $this->payments->markPaid($payment, $payload['id'], method: 'xendit');
            } elseif (in_array($status, ['expired', 'failed', 'canceled', 'cancelled'], true)) {
                if ($payment->status !== 'success') {
                    $payment->update(['status' => 'expired']);
                }
            }
        } finally {
            TenantContext::clear();
        }

        return ApiResponse::message('Xendit webhook diproses.');
    }
}
