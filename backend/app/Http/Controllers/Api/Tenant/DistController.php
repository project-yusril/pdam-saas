<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\DistributionReading;
use App\Models\DmaZone;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DistController extends Controller
{
    public function ingest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'readings' => ['required', 'array', 'max:100'],
            'readings.*.dma_zone_id' => ['required', 'integer', 'exists:dma_zones,id'],
            'readings.*.reading_at' => ['required', 'date'],
            'readings.*.flow_rate_m3h' => ['nullable', 'numeric'],
            'readings.*.pressure_bar' => ['nullable', 'numeric'],
            'readings.*.reservoir_level_percent' => ['nullable', 'numeric'],
            'readings.*.chlorine_residual' => ['nullable', 'numeric'],
        ]);

        $count = 0;
        foreach ($data['readings'] as $r) {
            DistributionReading::create([
                'pdam_org_id' => $request->user()->pdam_org_id,
                ...$r,
            ]);
            $count++;
        }

        return ApiResponse::success(['ingested' => $count]);
    }

    public function dmaDashboard(Request $request): JsonResponse
    {
        $dmas = DmaZone::with(['latestReading'])
            ->when($request->input('zone_id'), fn ($q, $v) => $q->where('zone_id', $v))
            ->get()
            ->map(fn ($dma) => [
                'id' => $dma->id,
                'code' => $dma->code,
                'name' => $dma->name,
                'connections' => (int) ($dma->total_connections ?? 0),
                'flow_rate' => (float) ($dma->latestReading?->flow_rate_m3h ?? 0),
                'pressure' => (float) ($dma->latestReading?->pressure_bar ?? 0),
                'reservoir' => (float) ($dma->latestReading?->reservoir_level_percent ?? 0),
                'chlorine' => (float) ($dma->latestReading?->chlorine_residual ?? 0),
                'last_reading' => $dma->latestReading?->reading_at?->toIso8601String(),
            ]);

        return ApiResponse::success($dmas);
    }
}
