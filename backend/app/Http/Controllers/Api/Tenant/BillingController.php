<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Customer;
use App\Services\BillingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * BillingController — generate & lihat tagihan (PRD 6.6, Fase 1.5).
 * Akses: Kabag Keuangan (generate) & internal (lihat).
 */
class BillingController extends Controller
{
    public function __construct(private BillingService $billing) {}

    /** Daftar tagihan (filter periode/status). */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);
        $query = Bill::with('customer:id,customer_number,full_name')->orderByDesc('period');

        if ($period = $request->query('period')) {
            $query->where('period', $period);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return ApiResponse::paginated($query->paginate($perPage));
    }

    /** Generate tagihan satu pelanggan untuk periode tertentu. */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'previous_reading' => ['required', 'integer', 'min:0'],
            'current_reading' => ['required', 'integer', 'min:0'],
        ]);

        $customer = Customer::find($data['customer_id']);
        if (! $customer) {
            return ApiResponse::error('NOT_FOUND', 'Pelanggan tidak ditemukan.', null, 404);
        }

        try {
            $bill = $this->billing->generateForCustomer(
                $customer,
                $data['period'],
                $data['previous_reading'],
                $data['current_reading'],
            );
        } catch (Throwable $e) {
            return ApiResponse::error('GENERATE_FAILED', $e->getMessage(), null, 422);
        }

        return ApiResponse::message('Tagihan dibuat.', $bill, 201);
    }

    public function show(Bill $bill): JsonResponse
    {
        return ApiResponse::success($bill->load('items', 'customer:id,customer_number,full_name'));
    }
}
