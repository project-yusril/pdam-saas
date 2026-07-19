<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Zone;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ZoneDashboardController — ringkasan statistik per wilayah. Fase 2.
 * Endpoint: GET /zones/{zone}/dashboard
 * GET /zones/dashboard  → semua zone sekaligus (untuk director)
 */
class ZoneDashboardController extends Controller
{
    /** Ringkasan semua wilayah (untuk director/admin). */
    public function all(Request $request): JsonResponse
    {
        $period = $request->query('period', now()->format('Y-m'));

        $zones = Zone::withCount([
            'customers',
            'customers as active_customers_count' => fn ($q) => $q->where('status', 'active'),
        ])->orderBy('is_main', 'desc')->orderBy('code')->get();

        $result = $zones->map(fn (Zone $z) => $this->buildSummary($z, $period));

        return ApiResponse::success($result);
    }

    /** Detail dashboard satu wilayah. */
    public function show(Request $request, Zone $zone): JsonResponse
    {
        $period = $request->query('period', now()->format('Y-m'));
        $zone->loadCount([
            'customers',
            'customers as active_customers_count' => fn ($q) => $q->where('status', 'active'),
        ]);

        return ApiResponse::success($this->buildSummary($zone, $period));
    }

    private function buildSummary(Zone $zone, string $period): array
    {
        // Customer IDs di zone ini
        $customerIds = Customer::where('zone_id', $zone->id)->pluck('id');

        // Tagihan periode berjalan
        $billsQuery = Bill::whereIn('customer_id', $customerIds)->where('period', $period);

        $totalBilled = (clone $billsQuery)->sum('amount_due');
        $totalPaid = (clone $billsQuery)->where('status', 'paid')->sum('amount_due');
        $totalUnpaid = (clone $billsQuery)->whereIn('status', ['unpaid', 'overdue'])->sum('amount_due');
        $overdueCount = (clone $billsQuery)->where('status', 'overdue')->count();
        $unpaidCount = (clone $billsQuery)->whereIn('status', ['unpaid', 'overdue'])->count();

        return [
            'zone_id' => $zone->id,
            'zone_code' => $zone->code,
            'zone_name' => $zone->name,
            'is_main' => $zone->is_main,
            'is_active' => $zone->is_active,
            'period' => $period,
            'total_customers' => $zone->customers_count ?? 0,
            'active_customers' => $zone->active_customers_count ?? 0,
            'billed_amount' => $totalBilled,
            'collected_amount' => $totalPaid,
            'outstanding_amount' => $totalUnpaid,
            'overdue_count' => $overdueCount,
            'unpaid_count' => $unpaidCount,
            'collection_rate' => $totalBilled > 0
                ? round(($totalPaid / $totalBilled) * 100, 2)
                : 0,
        ];
    }
}
