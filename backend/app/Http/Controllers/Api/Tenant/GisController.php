<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerMapStatusService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GisController extends Controller
{
    public function __construct(private CustomerMapStatusService $mapService) {}

    public function customerGeoJson(Request $request): JsonResponse
    {
        $query = Customer::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('status', '!=', 'inactive');

        if ($request->input('zone_id')) {
            $query->where('zone_id', $request->input('zone_id'));
        }

        if ($request->input('status')) {
            $colors = explode(',', $request->input('status'));
            $customerIds = [];

            foreach ($query->get() as $customer) {
                $color = $this->mapService->getStatusColor($customer);
                if (in_array($color, $colors)) {
                    $customerIds[] = $customer->id;
                }
            }

            $query = Customer::whereIn('id', $customerIds);
        }

        if ($request->input('meter_route_id')) {
            $query->where('meter_route_id', $request->input('meter_route_id'));
        }

        if ($request->input('tariff_category_id')) {
            $query->where('tariff_category_id', $request->input('tariff_category_id'));
        }

        if ($request->input('bbox')) {
            $bbox = explode(',', $request->input('bbox'));
            if (count($bbox) === 4) {
                $query->whereBetween('longitude', [(float) $bbox[0], (float) $bbox[2]])
                    ->whereBetween('latitude', [(float) $bbox[1], (float) $bbox[3]]);
            }
        }

        $customers = $query->limit(5000)->get();

        $features = $customers->map(fn ($c) => $this->mapService->buildGeoJsonFeature($c))->values();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    public function statusSummary(Request $request): JsonResponse
    {
        $customers = Customer::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('status', '!=', 'inactive');

        if ($request->input('zone_id')) {
            $customers->where('zone_id', $request->input('zone_id'));
        }

        $counts = [
            'green' => 0, 'white' => 0, 'yellow' => 0, 'red' => 0, 'black' => 0,
        ];

        foreach ($customers->cursor() as $customer) {
            $color = $this->mapService->getStatusColor($customer);
            $counts[$color]++;
        }

        return ApiResponse::success($counts);
    }
}
