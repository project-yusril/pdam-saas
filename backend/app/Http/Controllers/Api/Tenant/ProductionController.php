<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ProductionLog;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionController extends Controller
{
    public function ingest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'readings' => ['required', 'array', 'max:100'],
            'readings.*.production_date' => ['required', 'date'],
            'readings.*.raw_water_m3' => ['nullable', 'numeric'],
            'readings.*.treated_water_m3' => ['nullable', 'numeric'],
            'readings.*.distributed_water_m3' => ['nullable', 'numeric'],
            'readings.*.pump_runtime_hours' => ['nullable', 'numeric'],
            'readings.*.power_consumption_kwh' => ['nullable', 'numeric'],
            'readings.*.turbidity_ntu' => ['nullable', 'numeric'],
            'readings.*.ph' => ['nullable', 'numeric'],
            'readings.*.chlorine_residual' => ['nullable', 'numeric'],
            'readings.*.source_id' => ['nullable', 'integer'],
        ]);

        $count = 0;
        foreach ($data['readings'] as $r) {
            ProductionLog::updateOrCreate(
                ['pdam_org_id' => $request->user()->pdam_org_id, 'production_date' => $r['production_date'], 'source_id' => $r['source_id'] ?? null],
                $r
            );
            $count++;
        }

        return ApiResponse::success(['ingested' => $count]);
    }

    public function metrics(Request $request): JsonResponse
    {
        $month = $request->input('month', now()->format('Y-m'));

        $logs = ProductionLog::whereYear('production_date', substr($month, 0, 4))
            ->whereMonth('production_date', substr($month, 5, 2))
            ->orderBy('production_date')
            ->get();

        return ApiResponse::success([
            'period' => $month,
            'total_raw_water' => round($logs->sum('raw_water_m3'), 2),
            'total_treated' => round($logs->sum('treated_water_m3'), 2),
            'total_distributed' => round($logs->sum('distributed_water_m3'), 2),
            'avg_turbidity' => round($logs->avg('turbidity_ntu'), 2),
            'avg_ph' => round($logs->avg('ph'), 2),
            'avg_chlorine' => round($logs->avg('chlorine_residual'), 2),
            'total_pump_hours' => round($logs->sum('pump_runtime_hours'), 2),
            'total_power_kwh' => round($logs->sum('power_consumption_kwh'), 2),
            'daily' => $logs->map(fn ($l) => [
                'date' => $l->production_date->toDateString(),
                'raw' => (float) $l->raw_water_m3,
                'treated' => (float) $l->treated_water_m3,
                'distributed' => (float) $l->distributed_water_m3,
            ]),
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $today = ProductionLog::whereDate('production_date', today())->first();

        return ApiResponse::success([
            'today' => $today ? [
                'raw_water' => (float) $today->raw_water_m3,
                'treated' => (float) $today->treated_water_m3,
                'distributed' => (float) $today->distributed_water_m3,
                'turbidity' => (float) $today->turbidity_ntu,
                'ph' => (float) $today->ph,
                'chlorine' => (float) $today->chlorine_residual,
                'pump_hours' => (float) $today->pump_runtime_hours,
            ] : null,
            'status' => $today?->status ?? 'no_data',
        ]);
    }
}
