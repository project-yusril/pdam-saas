<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\SensorReading;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IotController extends Controller
{
    public function ingest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'readings' => ['required', 'array', 'max:1000'],
            'readings.*.device_id' => ['required', 'string', 'max:50'],
            'readings.*.meter_id' => ['nullable', 'integer'],
            'readings.*.customer_id' => ['nullable', 'integer'],
            'readings.*.value' => ['required', 'numeric', 'min:0'],
            'readings.*.flow_rate' => ['nullable', 'numeric'],
            'readings.*.reading_at' => ['required', 'date'],
            'readings.*.battery' => ['nullable', 'numeric'],
            'readings.*.signal_strength' => ['nullable', 'numeric'],
            'readings.*.source' => ['in:lorawan,nbiot,wifi'],
        ]);

        $count = 0;
        foreach ($data['readings'] as $reading) {
            SensorReading::create([
                'pdam_org_id' => $request->user()->pdam_org_id,
                'device_id' => $reading['device_id'],
                'meter_id' => $reading['meter_id'] ?? null,
                'customer_id' => $reading['customer_id'] ?? null,
                'value' => $reading['value'],
                'flow_rate' => $reading['flow_rate'] ?? null,
                'reading_at' => $reading['reading_at'],
                'battery' => $reading['battery'] ?? null,
                'signal_strength' => $reading['signal_strength'] ?? null,
                'source' => $reading['source'] ?? 'lorawan',
            ]);
            $count++;
        }

        return ApiResponse::success(['ingested' => $count]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $recent = SensorReading::with('customer:id,full_name,customer_number')
            ->orderByDesc('reading_at')
            ->limit(100)
            ->get()
            ->groupBy('device_id')
            ->map(function ($readings) {
                $latest = $readings->first();

                return [
                    'device_id' => $latest->device_id,
                    'customer' => $latest->customer?->full_name,
                    'latest_value' => (float) $latest->value,
                    'flow_rate' => (float) ($latest->flow_rate ?? 0),
                    'battery' => (float) ($latest->battery ?? 0),
                    'signal' => (float) ($latest->signal_strength ?? 0),
                    'last_reading' => $latest->reading_at->toIso8601String(),
                    'total_readings' => $readings->count(),
                ];
            })->values();

        $alerts = SensorReading::where('battery', '<', 20)
            ->orWhere('signal_strength', '<', -100)
            ->orderByDesc('reading_at')
            ->limit(20)
            ->get()
            ->map(fn ($r) => [
                'device_id' => $r->device_id,
                'alert_type' => $r->battery < 20 ? 'low_battery' : 'weak_signal',
                'value' => $r->battery < 20 ? $r->battery.'%' : $r->signal_strength.' dBm',
                'reading_at' => $r->reading_at->toIso8601String(),
            ]);

        return ApiResponse::success([
            'total_devices' => SensorReading::distinct('device_id')->count(),
            'readings_today' => SensorReading::whereDate('reading_at', today())->count(),
            'devices' => $recent,
            'alerts' => $alerts,
        ]);
    }
}
