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
use Illuminate\Validation\Rule;

/**
 * AddressController — CRUD alamat berjenjang + endpoint dropdown bertingkat.
 * Provinsi → Kota/Kab → Kecamatan → Kel/Desa → Jalan. PRD 1.1.
 */
class AddressController extends Controller
{
    public function provinces(Request $request): JsonResponse
    {
        $params = ListQueryParams::fromRequest($request, ['code', 'name']);

        $query = Province::forTenant($request->user()->pdam_org_id);
        $params->apply($query, ['name', 'code']);

        $paginator = $query->paginate($params->perPage, ['*'], 'page', $params->page);

        return ApiResponse::paginated($paginator);
    }

    public function cities(Request $request): JsonResponse
    {
        $request->validate(['province_id' => 'integer']);

        $params = ListQueryParams::fromRequest($request, ['name', 'type']);

        $query = City::forTenant($request->user()->pdam_org_id);
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

        $query = District::forTenant($request->user()->pdam_org_id);
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

        $query = Village::forTenant($request->user()->pdam_org_id);
        if ($request->has('district_id')) {
            $query->where('district_id', $request->input('district_id'));
        }
        $params->apply($query, ['name']);

        $paginator = $query->paginate($params->perPage, ['*'], 'page', $params->page);

        return ApiResponse::paginated($paginator);
    }

    public function streets(Request $request): JsonResponse
    {
        $request->validate(['village_id' => 'integer', 'meter_route_id' => 'integer']);

        $params = ListQueryParams::fromRequest($request, ['name']);

        $query = Street::forTenant($request->user()->pdam_org_id)->where('is_active', true);
        if ($request->has('village_id')) {
            $query->where('village_id', $request->input('village_id'));
        }
        // Filter jalan yang tergabung dalam rute baca meter tertentu (pivot meter_route_streets).
        if ($request->has('meter_route_id')) {
            $routeStreetIds = \Illuminate\Support\Facades\DB::table('meter_route_streets')
                ->where('meter_route_id', $request->integer('meter_route_id'))
                ->pluck('street_id');
            $query->whereIn('streets.id', $routeStreetIds);
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
        $data['pdam_org_id'] = $request->user()->pdam_org_id;

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

    /** ── CRUD Provinsi (master alamat global) ─────────────────────────── */
    public function storeProvince(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:provinces,code'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $data['pdam_org_id'] = $request->user()->pdam_org_id;

        $province = Province::create($data);

        return ApiResponse::success($province, status: 201);
    }

    public function updateProvince(Request $request, Province $province): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:10', Rule::unique('provinces', 'code')->ignore($province->id)],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        $province->update($data);

        return ApiResponse::success($province->fresh());
    }

    /** ── CRUD Kota/Kabupaten ──────────────────────────────────────────── */
    public function storeCity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'province_id' => ['required', 'integer', 'exists:provinces,id'],
            'code' => ['required', 'string', 'max:10', 'unique:cities,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::in(['kota', 'kabupaten'])],
        ]);

        $data['pdam_org_id'] = $request->user()->pdam_org_id;

        $city = City::create($data);

        return ApiResponse::success($city, status: 201);
    }

    public function updateCity(Request $request, City $city): JsonResponse
    {
        $data = $request->validate([
            'province_id' => ['sometimes', 'integer', 'exists:provinces,id'],
            'code' => ['sometimes', 'string', 'max:10', Rule::unique('cities', 'code')->ignore($city->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::in(['kota', 'kabupaten'])],
        ]);

        $city->update($data);

        return ApiResponse::success($city->fresh());
    }

    /** ── CRUD Kecamatan ───────────────────────────────────────────────── */
    public function storeDistrict(Request $request): JsonResponse
    {
        $data = $request->validate([
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'code' => ['required', 'string', 'max:15', 'unique:districts,code'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $data['pdam_org_id'] = $request->user()->pdam_org_id;

        $district = District::create($data);

        return ApiResponse::success($district, status: 201);
    }

    public function updateDistrict(Request $request, District $district): JsonResponse
    {
        $data = $request->validate([
            'city_id' => ['sometimes', 'integer', 'exists:cities,id'],
            'code' => ['sometimes', 'string', 'max:15', Rule::unique('districts', 'code')->ignore($district->id)],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        $district->update($data);

        return ApiResponse::success($district->fresh());
    }

    /** ── CRUD Desa/Kelurahan ──────────────────────────────────────────── */
    public function storeVillage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            'code' => ['required', 'string', 'max:20', 'unique:villages,code'],
            'name' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
        ]);

        $data['pdam_org_id'] = $request->user()->pdam_org_id;

        $village = Village::create($data);

        return ApiResponse::success($village, status: 201);
    }

    public function updateVillage(Request $request, Village $village): JsonResponse
    {
        $data = $request->validate([
            'district_id' => ['sometimes', 'integer', 'exists:districts,id'],
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('villages', 'code')->ignore($village->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
        ]);

        $village->update($data);

        return ApiResponse::success($village->fresh());
    }
}
