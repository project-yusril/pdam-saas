<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Bill;
use App\Models\Complaint;
use App\Models\Customer;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * CustomerPortalController — API mobile pelanggan (PRD 6.1, Fase 1.10).
 * Endpoint hanya melayani pelanggan yang sedang login (via user_id).
 */
class CustomerPortalController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    /** Dashboard: profil, tagihan bulan ini, konsumsi 6 bulan terakhir. */
    public function dashboard(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if (! $customer) {
            return ApiResponse::error('NOT_A_CUSTOMER', 'Akun ini bukan pelanggan.', null, 403);
        }

        $currentBill = Bill::where('customer_id', $customer->id)
            ->whereIn('status', ['unpaid', 'overdue'])
            ->orderByDesc('period')
            ->first();

        $consumptionTrend = Bill::where('customer_id', $customer->id)
            ->orderByDesc('period')
            ->limit(6)
            ->get(['period', 'consumption', 'amount_due'])
            ->reverse()
            ->values();

        return ApiResponse::success([
            'customer' => $customer->only(['id', 'customer_number', 'full_name', 'status', 'meter_serial_number']),
            'tariff' => $customer->tariffCategory?->only(['code', 'name']),
            'current_bill' => $currentBill,
            'consumption_trend' => $consumptionTrend,
        ]);
    }

    /** Riwayat tagihan pelanggan (paginated). */
    public function bills(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if (! $customer) {
            return ApiResponse::error('NOT_A_CUSTOMER', 'Akun ini bukan pelanggan.', null, 403);
        }

        $perPage = min((int) $request->query('per_page', 12), 50);
        $query = Bill::where('customer_id', $customer->id)
            ->with('items:id,bill_id,component,label,quantity,unit_price,amount')
            ->orderByDesc('period');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return ApiResponse::paginated($query->paginate($perPage));
    }

    public function billDetail(Request $request, Bill $bill): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if (! $customer || $bill->customer_id !== $customer->id) {
            return ApiResponse::error('FORBIDDEN', 'Tagihan bukan milik Anda.', null, 403);
        }

        return ApiResponse::success($bill->load('items'));
    }

    public function complaints(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if (! $customer) {
            return ApiResponse::error('NOT_A_CUSTOMER', 'Akun ini bukan pelanggan.', null, 403);
        }

        return ApiResponse::paginated(Complaint::where('customer_id', $customer->id)->latest()->paginate(20));
    }

    public function createComplaint(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if (! $customer) {
            return ApiResponse::error('NOT_A_CUSTOMER', 'Akun ini bukan pelanggan.', null, 403);
        }
        $data = $request->validate([
            'category' => ['required', 'string', 'max:30'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
        ]);
        $complaint = Complaint::create([
            ...$data,
            'customer_id' => $customer->id,
            'ticket_number' => 'TKT-'.strtoupper(Str::random(10)),
            'status' => 'open',
        ]);

        return ApiResponse::success($complaint, status: 201);
    }

    public function profile(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        return $customer ? ApiResponse::success($customer) : ApiResponse::error('NOT_A_CUSTOMER', 'Akun ini bukan pelanggan.', null, 403);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if (! $customer) {
            return ApiResponse::error('NOT_A_CUSTOMER', 'Akun ini bukan pelanggan.', null, 403);
        }
        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'address_detail' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
        $customer->update($data);

        return ApiResponse::success($customer->fresh());
    }

    /** Grafik konsumsi 6–12 bulan. */
    public function consumptionChart(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if (! $customer) {
            return ApiResponse::error('NOT_A_CUSTOMER', 'Akun ini bukan pelanggan.', null, 403);
        }

        $months = (int) $request->query('months', 12);
        $months = in_array($months, [6, 12]) ? $months : 12;

        $data = Bill::where('customer_id', $customer->id)
            ->orderByDesc('period')
            ->limit($months)
            ->get(['period', 'consumption', 'amount_due', 'status'])
            ->reverse()
            ->values();

        $average = $data->avg('consumption');
        $trend = $data->last()?->consumption - $data->first()?->consumption;

        return ApiResponse::success([
            'data' => $data,
            'average_consumption' => round($average, 2),
            'trend' => $trend ?? 0,
        ]);
    }

    /** Notifikasi pelanggan + mark read. */
    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();

        $perPage = min((int) $request->query('per_page', 20), 50);
        $notifications = AppNotification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ApiResponse::paginated($notifications);
    }

    public function markNotificationRead(Request $request, int $notificationId): JsonResponse
    {
        $notification = AppNotification::where('user_id', $request->user()->id)
            ->findOrFail($notificationId);

        $notification->update(['read_at' => now()]);

        return ApiResponse::message('Notifikasi ditandai sudah dibaca.');
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        AppNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::message('Semua notifikasi ditandai sudah dibaca.');
    }

    /** Inisiasi pembayaran satu tagihan → membuat Payment pending. */
    public function payBill(Request $request, Bill $bill): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if (! $customer || $bill->customer_id !== $customer->id) {
            return ApiResponse::error('FORBIDDEN', 'Tagihan bukan milik Anda.', null, 403);
        }

        if (in_array($bill->status, ['paid', 'waived'], true)) {
            return ApiResponse::error('ALREADY_SETTLED', 'Tagihan sudah tidak perlu dibayar.', null, 422);
        }

        $payment = $this->payments->createForBill($bill, channel: 'gateway');

        return ApiResponse::message('Pembayaran diinisiasi.', [
            'payment' => $payment->only(['id', 'payment_number', 'amount', 'status', 'midtrans_order_id']),
        ], 201);
    }

    /** Ambil pelanggan yang terhubung ke user login. */
    protected function resolveCustomer(Request $request): ?Customer
    {
        return Customer::where('user_id', $request->user()->id)
            ->with('tariffCategory:id,code,name')
            ->first();
    }
}
