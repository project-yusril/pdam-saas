<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Exceptions\MarketplaceException;
use App\Http\Controllers\Controller;
use App\Models\PdamOrganization;
use App\Services\MarketplaceService;
use App\Services\SaasPurchaseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketplaceController extends Controller
{
    public function catalog(Request $request, MarketplaceService $marketplace): JsonResponse
    {
        $organization = PdamOrganization::findOrFail($request->user()->pdam_org_id);

        return ApiResponse::success($marketplace->catalog($organization));
    }

    public function purchase(Request $request, SaasPurchaseService $purchases): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.type' => ['required', Rule::in(['module', 'bundle'])],
            'items.*.code' => ['required', 'string', 'max:50'],
        ]);
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || $key === '' || strlen($key) > 100) {
            return ApiResponse::error('IDEMPOTENCY_KEY_REQUIRED', 'Header Idempotency-Key wajib diisi.', null, 422);
        }

        try {
            $order = $purchases->create($request->user(), $data['items'], $key);
        } catch (MarketplaceException $exception) {
            return ApiResponse::error($exception->errorCode, $exception->getMessage(), $exception->details, 422);
        }

        return ApiResponse::success($order, status: $order->wasRecentlyCreated ? 201 : 200);
    }
}
