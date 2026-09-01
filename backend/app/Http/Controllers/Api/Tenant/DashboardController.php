<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Complaint;
use App\Models\Customer;
use App\Models\CustomerProspect;
use App\Models\FixedAsset;
use App\Models\Meter;
use App\Models\MeterAnomaly;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function director(): JsonResponse
    {
        $orgId = request()->user()->pdam_org_id;
        $now = now();

        $totalCustomers = Customer::count();
        $activeCustomers = Customer::where('status', 'active')->count();

        $revenueThisMonth = Bill::where('status', 'paid')
            ->whereMonth('updated_at', $now->month)
            ->whereYear('updated_at', $now->year)
            ->sum('amount_due');

        $outstanding = Bill::whereIn('status', ['unpaid', 'overdue'])->sum('amount_due');

        $waterSold = Bill::where('status', 'paid')
            ->whereMonth('updated_at', $now->month)
            ->whereYear('updated_at', $now->year)
            ->sum('consumption');

        $openComplaints = Complaint::whereIn('status', ['open', 'assigned'])->count();
        $openWorkOrders = WorkOrder::whereIn('status', ['open', 'assigned', 'in_progress'])->count();
        $totalAssets = FixedAsset::whereIn('status', ['aktif', 'rusak'])->sum('book_value');

        return ApiResponse::success([
            'customers' => ['total' => $totalCustomers, 'active' => $activeCustomers],
            'revenue_this_month' => (float) $revenueThisMonth,
            'outstanding' => (float) $outstanding,
            'water_sold_m3' => (float) $waterSold,
            'open_complaints' => $openComplaints,
            'open_work_orders' => $openWorkOrders,
            'total_asset_book_value' => (float) $totalAssets,
        ]);
    }

    public function finance(): JsonResponse
    {
        $orgId = request()->user()->pdam_org_id;
        $now = now();

        $revenueYtd = Bill::where('status', 'paid')->where('period', 'like', $now->year.'%')->sum('amount_due');
        $outstanding = Bill::whereIn('status', ['unpaid', 'overdue'])->sum('amount_due');
        $overdueCount = Bill::where('status', 'overdue')->count();
        $collectionsThisMonth = Bill::where('status', 'paid')->whereMonth('updated_at', $now->month)->whereYear('updated_at', $now->year)->sum('amount_due');
        $collectionRate = $revenueYtd > 0 ? round(($collectionsThisMonth / $revenueYtd) * 100, 1) : 0;

        return ApiResponse::success([
            'revenue_ytd' => (float) $revenueYtd,
            'outstanding' => (float) $outstanding,
            'overdue_bills_count' => $overdueCount,
            'collections_this_month' => (float) $collectionsThisMonth,
            'collection_rate_percent' => $collectionRate,
        ]);
    }

    public function operations(): JsonResponse
    {
        $orgId = request()->user()->pdam_org_id;
        $now = now();

        return ApiResponse::success([
            'total_customers' => Customer::count(),
            'active_customers' => Customer::where('status', 'active')->count(),
            'disconnected_customers' => Customer::where('status', 'disconnected')->count(),
            'pending_installations' => CustomerProspect::whereIn('status', ['surveying', 'survey_submitted', 'approved', 'payment_pending', 'payment_paid'])->count(),
            'open_work_orders' => WorkOrder::whereIn('status', ['open', 'assigned'])->count(),
            'in_progress_work_orders' => WorkOrder::where('status', 'in_progress')->count(),
            'complaints_open' => Complaint::whereIn('status', ['open', 'assigned'])->count(),
        ]);
    }

    public function metx(): JsonResponse
    {
        $orgId = request()->user()->pdam_org_id;

        return ApiResponse::success([
            'total_meters' => Meter::count(),
            'installed_meters' => Meter::where('status', 'terpasang')->count(),
            'anomalies_open' => MeterAnomaly::whereIn('status', ['open', 'reviewing'])->count(),
            'anomalies_confirmed' => MeterAnomaly::where('status', 'confirmed')->count(),
            'recommended_replace' => Meter::whereIn('condition', ['buram', 'macet', 'rusak'])->orWhere('tamper_status', 'tampered')->count(),
        ]);
    }

    public function executiveKpi(): JsonResponse
    {
        $orgId = request()->user()->pdam_org_id;
        $now = now();

        $sixMonthsAgo = $now->copy()->subMonths(5)->startOfMonth();

        $monthlyRevenue = \DB::table('bills')
            ->where('pdam_org_id', $orgId)
            ->where('status', 'paid')
            ->where('period', '>=', $sixMonthsAgo->format('Y-m'))
            ->selectRaw('period, SUM(amount_due) as total')
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $monthlyConsumption = \DB::table('bills')
            ->where('pdam_org_id', $orgId)
            ->where('status', 'paid')
            ->where('period', '>=', $sixMonthsAgo->format('Y-m'))
            ->selectRaw('period, SUM(consumption) as total')
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $customerGrowth = \DB::table('customers')
            ->where('pdam_org_id', $orgId)
            ->where('status', 'active')
            ->selectRaw("DATE_FORMAT(installation_date, '%Y-%m') as period, COUNT(*) as total")
            ->groupBy('period')
            ->orderBy('period', 'desc')
            ->limit(12)
            ->get();

        return ApiResponse::success([
            'monthly_revenue' => $monthlyRevenue,
            'monthly_consumption' => $monthlyConsumption,
            'customer_growth' => $customerGrowth,
            'outstanding_rate' => Bill::where('status', 'overdue')->count() > 0
                ? round((Bill::where('status', 'overdue')->count() / max(Bill::where('status', 'paid')->count(), 1)) * 100, 1)
                : 0,
        ]);
    }
}
