<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Meter;
use App\Models\MeterLifecycleEvent;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * MeterController — master meter fisik + assign ke pelanggan + lifecycle. Fase 6.1.
 * Lifecycle: gudang → terpasang → dicabut → kalibrasi ulang → afkir.
 * Setiap transisi dicatat di meter_lifecycle_events (audit).
 */
class MeterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 25), 100);
        $query = Meter::with('customer:id,customer_number,full_name')->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($condition = $request->query('condition')) {
            $query->where('condition', $condition);
        }
        if ($tamper = $request->query('tamper_status')) {
            $query->where('tamper_status', $tamper);
        }
        if ($search = $request->query('search')) {
            $query->where('serial_number', 'like', "%{$search}%");
        }

        return ApiResponse::paginated($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = $request->user()->pdam_org_id;

        $data = $request->validate([
            'serial_number' => ['required', 'string', 'max:100', Rule::unique('meters')->where('pdam_org_id', $orgId)],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'diameter' => ['nullable', 'string', 'max:10'],
            'install_year' => ['nullable', 'integer', 'min:1980', 'max:2100'],
            'condition' => ['nullable', 'in:baru,baik,buram,macet,rusak'],
            'warranty_until' => ['nullable', 'date'],
        ]);

        $meter = Meter::create($data + ['status' => 'gudang', 'condition' => $data['condition'] ?? 'baru']);

        $this->logEvent($meter, 'stored', null, 'gudang', null, $request->user()->id, 'Meter masuk gudang');

        return ApiResponse::message('Meter dibuat.', $meter, 201);
    }

    public function show(Meter $meter): JsonResponse
    {
        return ApiResponse::success(
            $meter->load('customer:id,customer_number,full_name', 'events')
        );
    }

    public function update(Request $request, Meter $meter): JsonResponse
    {
        $data = $request->validate([
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'diameter' => ['nullable', 'string', 'max:10'],
            'condition' => ['sometimes', 'in:baru,baik,buram,macet,rusak'],
            'location_note' => ['nullable', 'string', 'max:255'],
            'warranty_until' => ['nullable', 'date'],
        ]);

        $oldCondition = $meter->condition;
        $meter->update($data);

        if (isset($data['condition']) && $data['condition'] !== $oldCondition) {
            $this->logEvent($meter, 'condition_changed', $meter->status, $meter->status, null, $request->user()->id, "Kondisi: {$oldCondition} → {$data['condition']}");
        }

        return ApiResponse::success($meter->fresh());
    }

    /**
     * Pasang meter ke pelanggan (gudang → terpasang).
     * Integrasi dengan MTR: meter_serial_number pelanggan di-update.
     */
    public function assign(Request $request, Meter $meter): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'install_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if ($meter->status === 'afkir') {
            return ApiResponse::error('METER_SCRAPPED', 'Meter sudah diafkir, tidak bisa dipasang.', null, 422);
        }
        if ($meter->status === 'terpasang') {
            return ApiResponse::error('METER_INSTALLED', 'Meter sudah terpasang di pelanggan lain.', null, 422);
        }

        $customer = Customer::findOrFail($data['customer_id']);
        $from = $meter->status;

        DB::transaction(function () use ($meter, $customer, $data, $from, $request) {
            $meter->update([
                'status' => 'terpasang',
                'customer_id' => $customer->id,
                'install_date' => $data['install_date'] ?? now()->toDateString(),
                'install_year' => $meter->install_year ?? (int) now()->year,
            ]);
            $customer->update(['meter_serial_number' => $meter->serial_number]);

            $this->logEvent($meter, 'installed', $from, 'terpasang', $customer->id, $request->user()->id, $data['note'] ?? 'Meter dipasang');
        });

        return ApiResponse::message('Meter dipasang ke pelanggan.', $meter->fresh('customer'));
    }

    /** Cabut meter (terpasang → dicabut). */
    public function remove(Request $request, Meter $meter): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($meter->status !== 'terpasang') {
            return ApiResponse::error('NOT_INSTALLED', 'Meter tidak sedang terpasang.', null, 422);
        }

        $customerId = $meter->customer_id;
        $meter->update(['status' => 'dicabut', 'customer_id' => null]);

        $this->logEvent($meter, 'removed', 'terpasang', 'dicabut', $customerId, $request->user()->id, $data['reason'] ?? 'Meter dicabut');

        return ApiResponse::message('Meter dicabut.', $meter->fresh());
    }

    /** Kalibrasi ulang (catat tanggal + kembalikan ke gudang untuk pemakaian ulang). */
    public function recalibrate(Request $request, Meter $meter): JsonResponse
    {
        $data = $request->validate([
            'calibration_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $from = $meter->status;
        $meter->update([
            'last_calibration_date' => $data['calibration_date'] ?? now()->toDateString(),
            'condition' => 'baik',
            'status' => $meter->status === 'terpasang' ? 'terpasang' : 'gudang',
        ]);

        $this->logEvent($meter, 'recalibrated', $from, $meter->status, $meter->customer_id, $request->user()->id, $data['note'] ?? 'Meter dikalibrasi ulang');

        return ApiResponse::message('Meter dikalibrasi ulang.', $meter->fresh());
    }

    /** Afkir meter (akhir masa pakai). */
    public function scrap(Request $request, Meter $meter): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($meter->status === 'terpasang') {
            return ApiResponse::error('STILL_INSTALLED', 'Cabut meter dari pelanggan sebelum diafkir.', null, 422);
        }

        $from = $meter->status;
        $meter->update(['status' => 'afkir', 'condition' => 'rusak']);

        $this->logEvent($meter, 'scrapped', $from, 'afkir', null, $request->user()->id, $data['reason'] ?? 'Meter diafkir');

        return ApiResponse::message('Meter diafkir.', $meter->fresh());
    }

    private function logEvent(Meter $meter, string $event, ?string $from, ?string $to, ?int $customerId, ?int $by, ?string $note): void
    {
        MeterLifecycleEvent::create([
            'pdam_org_id' => $meter->pdam_org_id,
            'meter_id' => $meter->id,
            'event' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'customer_id' => $customerId,
            'note' => $note,
            'performed_by' => $by,
        ]);
    }
}
