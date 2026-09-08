<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\CustomerProspect;
use App\Models\InstallmentSchedule;
use App\Models\Payment;
use App\Services\Gateways\MidtransSnap;
use App\Services\PaymentService;
use App\Services\RefundException;
use App\Services\RefundService;
use App\Support\ApiResponse;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $payments,
        private MidtransSnap $midtrans,
        private RefundService $refunds,
    ) {}

    public function createPayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:monthly_bill,installation_fee,installment'],
            'bill_id' => ['required_if:type,monthly_bill', 'integer', 'exists:bills,id'],
            'prospect_id' => ['required_if:type,installation_fee', 'integer', 'exists:customer_prospects,id'],
            'schedule_id' => ['required_if:type,installment', 'integer', 'exists:installment_schedules,id'],
            'channel' => ['required', 'in:gateway,cash'],
            'method' => ['nullable', 'string'],
            'customer_name' => ['nullable', 'string'],
            'customer_email' => ['nullable', 'email'],
            'customer_phone' => ['nullable', 'string'],
        ]);

        if ($data['type'] === 'monthly_bill') {
            $bill = Bill::findOrFail($data['bill_id']);
            $payment = $this->payments->createForBill($bill, $data['channel'], $data['method'] ?? null);
        } elseif ($data['type'] === 'installation_fee') {
            $prospect = CustomerProspect::findOrFail($data['prospect_id']);
            $payment = $this->payments->createForInstallation($prospect, $data['channel'], $data['method'] ?? null);
        } else {
            $schedule = InstallmentSchedule::findOrFail($data['schedule_id']);
            $payment = $this->payments->createForInstallment($schedule, $data['channel'], $data['method'] ?? null);
        }

        $snap = null;
        if ($data['channel'] === 'gateway') {
            $snap = $this->midtrans->createTransaction($payment, [
                'name' => $data['customer_name'] ?? 'Pelanggan',
                'email' => $data['customer_email'] ?? null,
                'phone' => $data['customer_phone'] ?? null,
            ]);
        }

        return ApiResponse::success([
            'payment' => $payment,
            'snap_token' => $snap['token'] ?? null,
            'redirect_url' => $snap['redirect_url'] ?? null,
        ], status: 201);
    }

    public function cashPayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
        ]);

        $payment = Payment::findOrFail($data['payment_id']);

        if ($payment->channel !== 'cash') {
            throw ValidationException::withMessages(['channel' => 'Pembayaran ini bukan tunai.']);
        }

        $this->payments->markPaid(
            $payment,
            transactionId: 'CASH-'.now()->format('YmdHis').'-'.$payment->id,
            method: 'tunai',
        );

        return ApiResponse::success([
            'payment' => $payment->fresh(),
            'receipt_number' => 'KW-'.$payment->payment_number,
        ]);
    }

    public function checkStatus(string $orderId): JsonResponse
    {
        $status = $this->midtrans->getStatus($orderId);

        return ApiResponse::success([
            'order_id' => $orderId,
            'transaction_status' => $status['transaction_status'] ?? 'unknown',
            'raw' => $status,
        ]);
    }

    public function paymentHistory(Request $request): JsonResponse
    {
        $customer = Customer::where('user_id', $request->user()->id)->first();
        if (! $customer) {
            return ApiResponse::error('NOT_A_CUSTOMER', 'Akun ini bukan pelanggan.', null, 403);
        }

        $perPage = min((int) $request->query('per_page', 25), 100);
        $payments = Payment::where('customer_id', $customer->id)
            ->where('status', 'success')
            ->orderByDesc('paid_at')
            ->paginate($perPage);

        return ApiResponse::paginated($payments);
    }

    public function downloadReceipt(Payment $payment): JsonResponse
    {
        if ($payment->status !== 'success') {
            return ApiResponse::error('NOT_PAID', 'Pembayaran belum lunas.', null, 422);
        }

        $data = [
            'receipt_number' => 'KW-'.$payment->payment_number,
            'payment_number' => $payment->payment_number,
            'payment_type' => $payment->payment_type,
            'amount' => $payment->amount,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'method' => $payment->payment_method,
            'channel' => $payment->channel,
            'transaction_id' => $payment->midtrans_transaction_id,
            'organization' => TenantContext::org()?->name,
        ];

        return ApiResponse::success($data);
    }

    /** Daftar pengembalian dana Payment (keuangan). */
    public function refunds(Payment $payment): JsonResponse
    {
        $this->ensureOwnPayment($payment);

        return ApiResponse::success(['refunds' => $payment->refunds()->with('journal.lines')->get()]);
    }

    /**
     * Satu pengembalian dana (partial/full). Gated `business.refund.enabled`
     * (PRD §23: kebijakan limit/penyetujui) + permission `core.payment.refund`.
     * Jurnal DEBIT/KREDIT pembayaran asal dipembalikan (proporsional bila partial).
     */
    public function refund(Request $request, Payment $payment, RefundService $service): JsonResponse
    {
        $this->ensureOwnPayment($payment);

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'method' => ['nullable', 'in:counter,transfer,cash'],
        ]);

        try {
            $refund = $service->refund(
                $payment,
                $request->user()->id,
                isset($data['amount']) ? (float) $data['amount'] : null,
                $data['reason'] ?? '',
                $data['method'] ?? 'counter',
            );
        } catch (RefundException $e) {
            throw ValidationException::withMessages(['refund' => $e->getMessage()]);
        }

        return ApiResponse::message('Pengembalian diproses + jurnal dibuat.', $refund->load('journal.lines'), 201);
    }

    /** Payment yang di-resolve model binding bypass scoping; tolak refund lintas tenant. */
    private function ensureOwnPayment(Payment $payment): void
    {
        if (TenantContext::id() === null || (int) $payment->pdam_org_id !== (int) TenantContext::id()) {
            abort(404);
        }
    }
}
