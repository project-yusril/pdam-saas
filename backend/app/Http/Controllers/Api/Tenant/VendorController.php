<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorEvaluation;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Vendor::when($request->input('category'), fn ($q, $v) => $q->where('category', $v))
            ->when($request->has('is_blacklisted'), fn ($q) => $q->where('is_blacklisted', $request->boolean('is_blacklisted')))
            ->orderBy('name');

        $paginator = $query->paginate($request->input('per_page', 25));

        return ApiResponse::paginated($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => ['required', 'string', 'max:20', 'unique:vendors,code'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:50'],
        ]);

        $vendor = Vendor::create($data);

        return ApiResponse::success($vendor, status: 201);
    }

    public function show(Vendor $vendor): JsonResponse
    {
        return ApiResponse::success($vendor);
    }

    public function update(Request $request, Vendor $vendor): JsonResponse
    {
        $data = $request->validate([
            'name' => ['string', 'max:200'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string'],
            'category' => ['string', 'max:50'],
            'is_blacklisted' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $vendor->update($data);

        return ApiResponse::success($vendor);
    }

    public function evaluate(Request $request, Vendor $vendor): JsonResponse
    {
        $data = $request->validate([
            'quality_score' => ['required', 'numeric', 'between:0,5'],
            'delivery_score' => ['required', 'numeric', 'between:0,5'],
            'price_score' => ['required', 'numeric', 'between:0,5'],
            'compliance_score' => ['required', 'numeric', 'between:0,5'],
            'comments' => ['nullable', 'string'],
        ]);

        $overall = round(($data['quality_score'] + $data['delivery_score'] + $data['price_score'] + $data['compliance_score']) / 4, 1);

        VendorEvaluation::create([
            'pdam_org_id' => $vendor->pdam_org_id,
            'vendor_id' => $vendor->id,
            ...$data,
            'overall_score' => $overall,
        ]);

        $vendor->update(['rating' => $overall]);

        return ApiResponse::success(['rating' => $overall, 'vendor' => $vendor->fresh()]);
    }
}
