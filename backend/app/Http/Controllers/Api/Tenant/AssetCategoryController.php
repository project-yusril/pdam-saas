<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * AssetCategoryController — kelola klasifikasi aset PDAM. Fase 7.1.
 * Kategori khas: tanah (tak disusutkan), bangunan/IPA, mesin & pompa,
 * jaringan pipa, kendaraan, inventaris kantor, meter induk.
 */
class AssetCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            AssetCategory::withCount('assets')->orderBy('code')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = $request->user()->pdam_org_id;

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('asset_categories')->where('pdam_org_id', $orgId)],
            'name' => ['required', 'string', 'max:150'],
            'useful_life_months' => ['required', 'integer', 'min:1', 'max:1200'],
            'depreciation_method' => ['required', 'in:straight_line,declining_balance'],
            'declining_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_depreciable' => ['boolean'],
            'asset_account_code' => ['nullable', 'string', 'max:20'],
            'accumulation_account_code' => ['nullable', 'string', 'max:20'],
            'expense_account_code' => ['nullable', 'string', 'max:20'],
        ]);

        $category = AssetCategory::create($data + ['pdam_org_id' => $orgId]);

        return ApiResponse::message('Kategori aset dibuat.', $category, 201);
    }

    public function update(Request $request, AssetCategory $assetCategory): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'useful_life_months' => ['sometimes', 'integer', 'min:1', 'max:1200'],
            'depreciation_method' => ['sometimes', 'in:straight_line,declining_balance'],
            'declining_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_depreciable' => ['boolean'],
            'asset_account_code' => ['nullable', 'string', 'max:20'],
            'accumulation_account_code' => ['nullable', 'string', 'max:20'],
            'expense_account_code' => ['nullable', 'string', 'max:20'],
        ]);

        $assetCategory->update($data);

        return ApiResponse::success($assetCategory->fresh());
    }
}
