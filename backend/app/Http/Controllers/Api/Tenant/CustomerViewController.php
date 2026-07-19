<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Customer;
use App\Models\CustomerStatusHistory;
use App\Models\InstallmentPlan;
use App\Models\Payment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CustomerViewController extends Controller
{
    public function show(int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);

        $profile = $customer->load(['tariffCategory', 'zone', 'street.village.district.city.province']);

        $bills = $customer->bills()->orderByDesc('period')->limit(12)->get();
        $payments = Payment::where('customer_id', $customer->id)
            ->where('status', 'success')
            ->orderByDesc('paid_at')
            ->limit(10)
            ->get();
        $complaints = Complaint::where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
        $installments = InstallmentPlan::where('customer_id', $customer->id)
            ->with('schedules')
            ->orderByDesc('created_at')
            ->get();
        $statusHistory = CustomerStatusHistory::where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->get();
        $readings = $customer->readings()->orderByDesc('reading_date')->limit(12)->get();

        $consumptionTrend = $bills->sortBy('period')->values()->map(fn ($b) => [
            'period' => $b->period,
            'value' => $b->consumption,
        ]);

        $timeline = collect()
            ->concat($bills->map(fn ($b) => ['date' => $b->created_at, 'type' => 'bill', 'summary' => "Tagihan {$b->bill_number}: Rp ".number_format($b->amount_due)]))
            ->concat($payments->map(fn ($p) => ['date' => $p->paid_at, 'type' => 'payment', 'summary' => 'Bayar Rp '.number_format($p->amount)." ({$p->payment_method})"]))
            ->concat($complaints->map(fn ($c) => ['date' => $c->created_at, 'type' => 'complaint', 'summary' => "Pengaduan #{$c->ticket_number}"]))
            ->concat($statusHistory->map(fn ($s) => ['date' => $s->created_at, 'type' => 'status_change', 'summary' => "Status: {$s->from_status} → {$s->to_status}"]))
            ->sortByDesc('date')
            ->values()
            ->take(50);

        return ApiResponse::success([
            'profile' => $profile,
            'bills_summary' => [
                'total' => $bills->count(),
                'unpaid' => $bills->whereIn('status', ['unpaid', 'overdue'])->count(),
                'total_unpaid' => $bills->whereIn('status', ['unpaid', 'overdue'])->sum('amount_due'),
            ],
            'consumption_trend' => $consumptionTrend,
            'recent_bills' => $bills,
            'recent_payments' => $payments,
            'complaints' => $complaints,
            'installments' => $installments,
            'status_history' => $statusHistory,
            'meter_readings' => $readings,
            'timeline' => $timeline,
        ]);
    }

    public function quickSearch(Request $request): JsonResponse
    {
        $q = $request->query('q');
        if (! $q || strlen($q) < 2) {
            return ApiResponse::success([]);
        }

        $results = Customer::where('customer_number', 'like', "%{$q}%")
            ->orWhere('full_name', 'like', "%{$q}%")
            ->orWhere('phone', 'like', "%{$q}%")
            ->limit(10)
            ->get(['id', 'customer_number', 'full_name', 'phone', 'status']);

        return ApiResponse::success($results);
    }

    public function aggregation(int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);

        return ApiResponse::success([
            'customer' => $customer->only(['id', 'customer_number', 'full_name', 'status']),
            'bill_stats' => [
                'current_month' => $customer->bills()->where('period', now()->format('Y-m'))->sum('amount_due'),
                'total_unpaid' => $customer->bills()->whereIn('status', ['unpaid', 'overdue'])->sum('amount_due'),
                'total_paid_ytd' => $customer->bills()->where('status', 'paid')->whereYear('period', now()->year)->sum('amount_due'),
            ],
            'consumption' => [
                'last_reading' => $customer->readings()->latest('reading_date')->first()?->reading_value ?? 0,
                'avg_6mo' => round($customer->bills()->orderByDesc('period')->limit(6)->avg('consumption') ?? 0, 2),
            ],
            'complaints_open' => $customer->complaints()->whereIn('status', ['open', 'assigned'])->count(),
        ]);
    }
}
