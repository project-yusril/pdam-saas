<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\MeterReading;
use App\Models\MeterRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * MeterRouteWebController — UI web MTR (Fase 4.1 & 4.3).
 * - index: daftar rute baca meter + jumlah jalan/petugas/pelanggan
 * - dashboard: progress baca per rute untuk periode terpilih (chart/gauge)
 */
class MeterRouteWebController extends Controller
{
    public function index(): View
    {
        $routes = MeterRoute::withCount('streets', 'assignments')
            ->with('zone:id,code,name')
            ->orderBy('code')
            ->get();

        // Hitung jumlah pelanggan per rute (aktif).
        $customersByRoute = Customer::where('status', 'active')
            ->whereNotNull('meter_route_id')
            ->select('meter_route_id', DB::raw('COUNT(*) as total'))
            ->groupBy('meter_route_id')
            ->pluck('total', 'meter_route_id');

        return view('admin.meter-routes.index', compact('routes', 'customersByRoute'));
    }

    public function dashboard(Request $request): View
    {
        $period = $request->query('period', now()->format('Y-m'));

        $customersByRoute = Customer::where('status', 'active')
            ->whereNotNull('meter_route_id')
            ->select('meter_route_id', DB::raw('COUNT(*) as total'))
            ->groupBy('meter_route_id')
            ->pluck('total', 'meter_route_id');

        $readByRoute = MeterReading::query()
            ->where('meter_readings.period', $period)
            ->join('customers', 'customers.id', '=', 'meter_readings.customer_id')
            ->whereNotNull('customers.meter_route_id')
            ->select(
                'customers.meter_route_id as route_id',
                DB::raw('COUNT(*) as read_count'),
                DB::raw('SUM(CASE WHEN meter_readings.is_flagged = 1 THEN 1 ELSE 0 END) as flagged_count'),
            )
            ->groupBy('customers.meter_route_id')
            ->get()
            ->keyBy('route_id');

        $routes = MeterRoute::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);

        $rows = $routes->map(function (MeterRoute $route) use ($customersByRoute, $readByRoute) {
            $total = (int) ($customersByRoute[$route->id] ?? 0);
            $row = $readByRoute[$route->id] ?? null;
            $read = (int) ($row->read_count ?? 0);
            $flagged = (int) ($row->flagged_count ?? 0);

            return [
                'code' => $route->code,
                'name' => $route->name,
                'total' => $total,
                'read' => $read,
                'remaining' => max(0, $total - $read),
                'flagged' => $flagged,
                'percent' => $total > 0 ? round($read / $total * 100, 1) : 0.0,
            ];
        });

        return view('admin.meter-routes.dashboard', compact('period', 'rows'));
    }
}
