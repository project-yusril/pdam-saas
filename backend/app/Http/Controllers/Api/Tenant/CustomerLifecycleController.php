<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerLifecycleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * CustomerLifecycleController — perubahan siklus hidup pelanggan aktif:
 * pemutusan (isolir/terminasi), penyambungan kembali, dan balik nama.
 * PRD lifecycle Fase 2.6.
 */
class CustomerLifecycleController extends Controller
{
    public function __construct(private CustomerLifecycleService $service) {}

    /** Putuskan sambungan pelanggan. */
    public function disconnect(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:isolir,terminated,temporary_closed'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $this->service->disconnect($customer, $data['type'], $data['reason'] ?? null, $request->user()->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error('INVALID_STATE', $e->getMessage(), null, 422);
        }

        return ApiResponse::message('Sambungan diputus.', $result, 201);
    }

    /** Sambung kembali (buka isolir). */
    public function reconnect(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'fee' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $result = $this->service->reconnect($customer, (float) ($data['fee'] ?? 0), $request->user()->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error('INVALID_STATE', $e->getMessage(), null, 422);
        }

        return ApiResponse::message('Sambungan disambung kembali.', $result, 201);
    }

    /** Balik nama kepemilikan pelanggan. */
    public function transferOwnership(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'new_owner_name' => ['required', 'string', 'max:150'],
            'new_owner_nik' => ['nullable', 'string', 'size:16'],
            'new_owner_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $result = $this->service->transferOwnership($customer, $data, $request->user()->id);

        return ApiResponse::message('Balik nama diproses.', $result, 201);
    }
}
