<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Geo\FieldLocationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FieldLocationController — titik GPS "Lapor Lokasi" dari aplikasi mobile
 * (GpsHelper.getCurrentPosition) → technician_locations → peta GIS Jaringan.
 * Kontrak SELARAS dengan payload baca meter / survey yang memang sudah
 * membawa koordinat (satu sumber kebenaran: GPS asli lapangan).
 */
class FieldLocationController extends Controller
{
    public function store(Request $request, FieldLocationService $service): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);

        $row = $service->report(
            $request->user(),
            (float) $data['latitude'],
            (float) $data['longitude'],
            isset($data['accuracy']) ? (float) $data['accuracy'] : null,
        );

        return ApiResponse::message('Lokasi tercatat.', [
            'user_id' => $row->user_id,
            'updated_at' => $row->updated_at?->toIso8601String(),
        ], 201);
    }
}
