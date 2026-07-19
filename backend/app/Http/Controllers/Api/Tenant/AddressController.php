<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\District;
use App\Models\Province;
use App\Models\Street;
use App\Models\Village;
use App\Support\ApiResponse;
use App\Support\ListQueryParams;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AddressController — CRUD alamat berjenjang + endpoint dropdown bertingkat.
 * Provinsi → Kota/Kab → Kecamatan → Kel/Desa → Jalan. PRD 1.1.
 */
class AddressController extends Controller
{
    public function provinces(Request $request): JsonResponse
    {
        $params = ListQueryParams::fromRequest($request, ['code', 'name']);

        $query = Province::query();
        $params->apply($query, ['name', 'code']);

        $paginator = $query->paginate($params->perPage, ['*'], 'page', $params->page);

        return ApiResponse::paginated($paginator);
    }

    public function cities(Request $request): JsonResponse
    {
        $request->validate(['province_id' => 'integer']);

        $params = ListQueryParams::fromRequest($request, ['name', 'type']);

        $query = City::query();
        if ($request->has('province_id')) {
            $query->where('province_id', $request->input('province_id'));
        }
        $params->apply($query, ['name']);

        $paginator = $query->paginate($params->perPage, ['*'], 'page', $params->page);

        return ApiResponse::paginated($paginator);
    }

    public function districts(Request $request): JsonResponse
    {
        $request->validate(['city_id' => 'integer']);

        $params = ListQueryParams::fromRequest($request, ['name']);

        $query = District::query();
        if ($request->has('city_id')) {
            $query->where('city_id', $request->input('city_id'));
        }
        $params->apply($query, ['name']);

        $paginator = $query->paginate($params->perPage, ['*'], 'page', $params->page);

        return ApiResponse::paginated($paginator);
    }

    public function villages(Request $request): JsonResponse
    {
        $request->validate(['district_id' => 'integer']);

        $params = ListQueryParams::fromRequest($request, ['name']);

        $query = Village::query();
        if ($request->has('district_id')) {
            $query->where('district_id', $request->input('district_id'));
        }
        $params->apply($query, ['name']);

        $paginator = $query->paginate($params->perPage, ['*'], 'page', $params->page);

        return ApiResponse::paginated($paginator);
    }

    public function streets(Request $request): JsonResponse
    {
        $request->validate(['village_id' => 'integer']);

        $params = ListQueryParams::fromRequest($request, ['name']);

        $query = Street::where('is_active', true);
        if ($request->has('village_id')) {
            $query->where('village_id', $request->input('village_id'));
        }
        $params->apply($query, ['name']);

        $paginator = $query->paginate($params->perPage, ['*'], 'page', $params->page);

        return ApiResponse::paginated($paginator);
    }

    public function storeStreet(Request $request): JsonResponse
    {
        $data = $request->validate([
            'village_id' => ['required', 'integer', 'exists:villages,id'],
            'name' => ['required', 'string', 'max:200'],
        ]);

        $data['is_active'] = true;

        $street = Street::create($data);

        return ApiResponse::success($street, status: 201);
    }

    public function updateStreet(Request $request, Street $street): JsonResponse
    {
        $data = $request->validate([
            'village_id' => ['integer', 'exists:villages,id'],
            'name' => ['string', 'max:200'],
            'is_active' => ['boolean'],
        ]);

        $street->update($data);

        return ApiResponse::success($street);
    }
}
