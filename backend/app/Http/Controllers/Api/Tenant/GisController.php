<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerMapStatusService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GisController extends Controller
{
    public function __construct(private CustomerMapStatusService $mapService) {}

    public function customerGeoJson(Request $request): JsonResponse
    {
        $customers = $this->queryFromRequest($request)->get();

        // Filter warna status: klasifikasi batch 1 query agregate (bukan per pelanggan).
        if ($request->filled('status')) {
            $wanted = explode(',', (string) $request->input('status'));
            $rows = $this->mapService->classifyBatch($customers);
            $customers = $customers->filter(
                fn (Customer $c) => in_array($rows[$c->id]['status_color'] ?? '', $wanted, true)
            );
        }

        return response()->json($this->mapService->featureCollection($customers));
    }

    public function statusSummary(Request $request): JsonResponse
    {
        $counts = $this->mapService->statusCounts($this->queryFromRequest($request)->get());

        return ApiResponse::success($counts);
    }

    /** Basis query pelanggan yang layak tampil di peta. */
    private function queryFromRequest(Request $request): Builder
    {
        $query = Customer::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotIn('status', CustomerMapStatusService::EXCLUDED_STATUSES);

        if ($request->input('zone_id')) {
            $query->where('zone_id', $request->input('zone_id'));
        }
        if ($request->input('meter_route_id')) {
            $query->where('meter_route_id', $request->input('meter_route_id'));
        }
        if ($request->input('tariff_category_id')) {
            $query->where('tariff_category_id', $request->input('tariff_category_id'));
        }
        if ($request->input('bbox')) {
            $bbox = explode(',', (string) $request->input('bbox'));
            if (count($bbox) === 4) {
                $query->whereBetween('longitude', [(float) $bbox[0], (float) $bbox[2]])
                    ->whereBetween('latitude', [(float) $bbox[1], (float) $bbox[3]]);
            }
        }

        return $query->limit(5000);
    }
}
