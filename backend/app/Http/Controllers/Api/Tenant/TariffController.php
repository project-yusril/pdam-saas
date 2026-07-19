<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TariffCategory;
use App\Models\TariffTier;
use App\Support\ApiResponse;
use App\Support\ListQueryParams;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TariffController — CRUD golongan tarif + tier. Fase 1.3.
 */
class TariffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $params = ListQueryParams::fromRequest($request, ['code', 'name']);
        $query = TariffCategory::with('tiers');
        $params->apply($query, ['code', 'name']);

        $paginator = $query->paginate($params->perPage, ['*'], 'page', $params->page);

        return ApiResponse::paginated($paginator);
    }

    public function show(TariffCategory $tariffCategory): JsonResponse
    {
        $tariffCategory->load('tiers');

        return ApiResponse::success($tariffCategory);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:tariff_categories,code'],
            'name' => ['required', 'string', 'max:100'],
            'group_type' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'abonemen' => ['required', 'numeric', 'min:0'],
            'meter_maintenance_fee' => ['required', 'numeric', 'min:0'],
            'admin_fee' => ['required', 'numeric', 'min:0'],
            'minimum_usage_m3' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*.tier_order' => ['required', 'integer', 'min:1'],
            'tiers.*.min_usage' => ['required', 'integer', 'min:0'],
            'tiers.*.max_usage' => ['required', 'integer', 'gt:tiers.*.min_usage'],
            'tiers.*.price_per_m3' => ['required', 'numeric', 'min:0'],
            'tiers.*.effective_date' => ['nullable', 'date'],
        ]);

        $tiers = $data['tiers'];
        unset($data['tiers']);

        $category = TariffCategory::create($data);
        foreach ($tiers as $tier) {
            $tier['tariff_category_id'] = $category->id;
            TariffTier::create($tier);
        }

        $category->load('tiers');

        return ApiResponse::success($category, status: 201);
    }

    public function update(Request $request, TariffCategory $tariffCategory): JsonResponse
    {
        $data = $request->validate([
            'code' => ['string', 'max:20', 'unique:tariff_categories,code,'.$tariffCategory->id],
            'name' => ['string', 'max:100'],
            'group_type' => ['string', 'max:50'],
            'description' => ['nullable', 'string'],
            'abonemen' => ['numeric', 'min:0'],
            'meter_maintenance_fee' => ['numeric', 'min:0'],
            'admin_fee' => ['numeric', 'min:0'],
            'minimum_usage_m3' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
            'tiers' => ['array', 'min:1'],
            'tiers.*.id' => ['nullable', 'integer', 'exists:tariff_tiers,id'],
            'tiers.*.tier_order' => ['required', 'integer', 'min:1'],
            'tiers.*.min_usage' => ['required', 'integer', 'min:0'],
            'tiers.*.max_usage' => ['required', 'integer', 'gt:tiers.*.min_usage'],
            'tiers.*.price_per_m3' => ['required', 'numeric', 'min:0'],
            'tiers.*.effective_date' => ['nullable', 'date'],
        ]);

        $tiers = $data['tiers'] ?? null;
        unset($data['tiers']);

        $tariffCategory->update($data);

        if ($tiers !== null) {
            $tariffCategory->tiers()->delete();
            foreach ($tiers as $tier) {
                $tier['tariff_category_id'] = $tariffCategory->id;
                unset($tier['id']);
                TariffTier::create($tier);
            }
        }

        $tariffCategory->load('tiers');

        return ApiResponse::success($tariffCategory);
    }
}
