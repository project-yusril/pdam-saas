<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\NrwBalance;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NrwController extends Controller
{
    public function calculate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dma_zone_id' => ['required', 'integer', 'exists:dma_zones,id'],
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'system_input_m3' => ['required', 'numeric', 'min:0'],
            'billed_metered_m3' => ['required', 'numeric', 'min:0'],
            'unbilled_metered_m3' => ['nullable', 'numeric', 'min:0'],
            'unbilled_unmetered_m3' => ['nullable', 'numeric', 'min:0'],
        ]);

        $unbilledMetered = $data['unbilled_metered_m3'] ?? 0;
        $unbilledUnmetered = $data['unbilled_unmetered_m3'] ?? 0;
        $authorised = $data['billed_metered_m3'] + $unbilledMetered + $unbilledUnmetered;
        $waterLosses = $data['system_input_m3'] - $authorised;
        $nrwPercent = $data['system_input_m3'] > 0 ? round(($waterLosses / $data['system_input_m3']) * 100, 2) : 0;

        $balance = NrwBalance::updateOrCreate(
            ['pdam_org_id' => $request->user()->pdam_org_id, 'dma_zone_id' => $data['dma_zone_id'], 'period' => $data['period']],
            [
                'system_input_m3' => $data['system_input_m3'],
                'billed_metered_m3' => $data['billed_metered_m3'],
                'unbilled_metered_m3' => $unbilledMetered,
                'unbilled_unmetered_m3' => $unbilledUnmetered,
                'authorised_consumption_m3' => $authorised,
                'water_losses_m3' => max(0, $waterLosses),
                'nrw_percentage' => $nrwPercent,
            ]
        );

        return ApiResponse::success($balance);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $balances = NrwBalance::with('dmaZone:id,code,name')
            ->when($request->input('period'), fn ($q, $v) => $q->where('period', $v))
            ->orderByDesc('nrw_percentage')
            ->limit(20)
            ->get()
            ->map(fn ($b) => [
                'dma' => $b->dmaZone?->name,
                'period' => $b->period,
                'system_input' => (float) $b->system_input_m3,
                'billed' => (float) $b->billed_metered_m3,
                'water_losses' => (float) $b->water_losses_m3,
                'nrw_percent' => (float) $b->nrw_percentage,
                'status' => $b->nrw_percentage > 30 ? 'critical' : ($b->nrw_percentage > 20 ? 'warning' : 'good'),
            ]);

        $avgNrw = $balances->avg('nrw_percent');

        return ApiResponse::success([
            'avg_nrw_percent' => round($avgNrw ?? 0, 2),
            'total_dmas' => $balances->count(),
            'critical_dmas' => $balances->where('status', 'critical')->count(),
            'details' => $balances,
        ]);
    }
}
