<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\District;
use App\Models\Province;
use App\Models\Street;
use App\Models\Village;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileDropdownController extends Controller
{
    /** Dropdown provinsi — ringan, tanpa paginasi. */
    public function provinces(): JsonResponse
    {
        $data = Province::select('id', 'code', 'name')->orderBy('name')->get();

        return ApiResponse::success($data);
    }

    /** Dropdown kota/kab — filter by province_id. */
    public function cities(Request $request): JsonResponse
    {
        $request->validate(['province_id' => 'required|integer']);
        $data = City::select('id', 'code', 'name', 'type')
            ->where('province_id', $request->input('province_id'))
            ->orderBy('name')
            ->get();

        return ApiResponse::success($data);
    }

    /** Dropdown kecamatan — filter by city_id. */
    public function districts(Request $request): JsonResponse
    {
        $request->validate(['city_id' => 'required|integer']);
        $data = District::select('id', 'code', 'name')
            ->where('city_id', $request->input('city_id'))
            ->orderBy('name')
            ->get();

        return ApiResponse::success($data);
    }

    /** Dropdown kel/desa — filter by district_id. */
    public function villages(Request $request): JsonResponse
    {
        $request->validate(['district_id' => 'required|integer']);
        $data = Village::select('id', 'code', 'name')
            ->where('district_id', $request->input('district_id'))
            ->orderBy('name')
            ->get();

        return ApiResponse::success($data);
    }

    /** Dropdown jalan — filter by village_id. */
    public function streets(Request $request): JsonResponse
    {
        $request->validate(['village_id' => 'required|integer']);
        $data = Street::select('id', 'name')
            ->where('village_id', $request->input('village_id'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return ApiResponse::success($data);
    }
}
