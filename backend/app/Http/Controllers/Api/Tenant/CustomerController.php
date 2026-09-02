<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerStatusHistory;
use App\Support\ApiResponse;
use App\Support\ListQueryParams;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CustomerController — CRUD pelanggan + nomor otomatis. Fase 1.4.
 */
class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $params = ListQueryParams::fromRequest($request, ['customer_number', 'full_name', 'status']);

        $query = Customer::with(['tariffCategory', 'zone'])
            ->when($request->input('zone_id'), fn ($q, $v) => $q->where('zone_id', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('tariff_category_id'), fn ($q, $v) => $q->where('tariff_category_id', $v));

        $params->apply($query, ['customer_number', 'full_name']);

        $paginator = $query->paginate($params->perPage, ['*'], 'page', $params->page);

        return ApiResponse::paginated($paginator);
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer->load(['tariffCategory', 'zone', 'statusHistory' => fn ($q) => $q->latest()]);

        return ApiResponse::success($customer);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'zone_id' => ['required', 'integer', 'exists:zones,id'],
            'street_id' => ['nullable', 'integer', 'exists:streets,id'],
            'address_detail' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'tariff_category_id' => ['required', 'integer', 'exists:tariff_categories,id'],
            'meter_serial_number' => ['nullable', 'string', 'max:50'],
            'meter_route_id' => ['nullable', 'integer', 'exists:meter_routes,id'],
            'installation_date' => ['nullable', 'date'],
        ]);

        $data['customer_number'] = $this->generateCustomerNumber();
        $data['status'] = 'active';
        $data['initial_reading'] = 0;

        $customer = Customer::create($data);

        CustomerStatusHistory::create([
            'pdam_org_id' => $customer->pdam_org_id,
            'customer_id' => $customer->id,
            'from_status' => null,
            'to_status' => 'active',
            'reason' => 'Pendaftaran baru',
        ]);

        $customer->load(['tariffCategory', 'zone']);

        return ApiResponse::success($customer, status: 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'zone_id' => ['integer', 'exists:zones,id'],
            'street_id' => ['nullable', 'integer', 'exists:streets,id'],
            'address_detail' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'tariff_category_id' => ['integer', 'exists:tariff_categories,id'],
            'meter_serial_number' => ['nullable', 'string', 'max:50'],
            'meter_route_id' => ['nullable', 'integer', 'exists:meter_routes,id'],
        ]);

        $customer->update($data);
        $customer->load(['tariffCategory', 'zone']);

        return ApiResponse::success($customer);
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $customer->delete();

        return ApiResponse::message('Pelanggan dihapus (soft-delete).');
    }

    public function statusHistory(Customer $customer): JsonResponse
    {
        $history = $customer->statusHistory()->latest()->get();

        return ApiResponse::success($history);
    }

    public function manualReading(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'reading_value' => ['required', 'integer', 'min:0'],
            'reading_date' => ['required', 'date'],
        ]);

        $customer->readings()->create([
            'pdam_org_id' => $customer->pdam_org_id,
            'reading_value' => $data['reading_value'],
            'reading_date' => $data['reading_date'],
            'source' => 'manual',
        ]);

        return ApiResponse::success($customer->fresh()->load('readings'));
    }

    private function generateCustomerNumber(): string
    {
        $prefix = date('ym');
        $last = Customer::where('customer_number', 'like', "$prefix%")
            ->orderBy('customer_number', 'desc')
            ->first();

        if ($last) {
            $seq = (int) substr($last->customer_number, 4) + 1;
        } else {
            $seq = 1;
        }

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
