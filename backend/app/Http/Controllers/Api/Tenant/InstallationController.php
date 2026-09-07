<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CustomerProspect;
use App\Models\InstallationMaterialOrder;
use App\Models\InstallationSchedule;
use App\Services\InstallationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * InstallationController — penjadwalan, material (reservasi + stock-out +
 * jurnal), & aktivasi pemasangan baru (PRD 3.1 tahap 6).
 * payment_paid → order-materials (reserved) → installation_scheduled →
 * complete (stock-out + jurnal) → installed → active.
 */
class InstallationController extends Controller
{
    public function __construct(private InstallationService $service) {}

    /**
     * Kepala Teknik/Gudang: order material pemasangan.
     * Tanpa modul WH / tanpa material valid → planning_only; lainnya dibuat
     * order `reserved` dengan cek ketersediaan stok di gudang utama.
     */
    public function orderMaterials(Request $request, CustomerProspect $prospect): JsonResponse
    {
        $data = $request->validate([
            'materials' => ['nullable', 'array', 'min:1', 'max:50'],
            'materials.*.material_id' => ['nullable', 'integer'],
            'materials.*.material_code' => ['nullable', 'string', 'max:50'],
            'materials.*.qty' => ['nullable', 'numeric', 'gt:0'],
            'materials.*.quantity' => ['nullable', 'numeric', 'gt:0'],
        ]);

        try {
            $result = $this->service->orderMaterials($prospect, $data['materials'] ?? [], $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error('INVALID_STATE', $e->getMessage(), null, 422);
        }

        $message = match (true) {
            ($result['mode'] ?? '') === 'reserved' && ($result['stock_issued'] ?? false) => 'Material sudah di-stock-out untuk order sebelumnya.',
            ($result['mode'] ?? '') === 'reserved' && ($result['already_reserved'] ?? false) => 'Material sudah direservasi sebelumnya.',
            ($result['mode'] ?? '') === 'reserved' => 'Material direservasi di gudang utama.',
            default => 'Material hanya rencana (planning_only): '.($result['reason'] ?? 'unknown').'.',
        };

        return ApiResponse::message($message, $result);
    }

    /** Daftar order material untuk prospek (semua status). */
    public function materialOrders(CustomerProspect $prospect): JsonResponse
    {
        $orders = InstallationMaterialOrder::where('prospect_id', $prospect->id)->latest('id')->get();

        return ApiResponse::success($orders);
    }

    /** Stock-out manual order material (biasanya otomatis saat complete). */
    public function issueMaterials(Request $request, InstallationMaterialOrder $order): JsonResponse
    {
        try {
            $result = $this->service->issueMaterials($order, $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error('INVALID_STATE', $e->getMessage(), null, 422);
        }

        return ApiResponse::message('Stok material dikeluarkan dan jurnal dibuat.', $result);
    }

    /** Batalkan order material yang belum di-stock-out. */
    public function cancelMaterials(Request $request, InstallationMaterialOrder $order): JsonResponse
    {
        try {
            $result = $this->service->cancelMaterials($order);
        } catch (RuntimeException $e) {
            return ApiResponse::error('INVALID_STATE', $e->getMessage(), null, 422);
        }

        return ApiResponse::message('Order material dibatalkan.', $result);
    }

    /** Kepala Teknik: jadwalkan pemasangan untuk prospek yang sudah bayar. */
    public function schedule(Request $request, CustomerProspect $prospect): JsonResponse
    {
        $data = $request->validate([
            'scheduled_date' => ['required', 'date'],
            'technician_id' => ['required', 'integer'],
        ]);

        try {
            $schedule = $this->service->schedule($prospect, $data['scheduled_date'], $data['technician_id']);
        } catch (RuntimeException $e) {
            return ApiResponse::error('INVALID_STATE', $e->getMessage(), null, 422);
        }

        return ApiResponse::message('Pemasangan dijadwalkan.', $schedule, 201);
    }

    /** Teknisi: tandai pemasangan selesai — stok material di-out + jurnal bila ada order reserved. */
    public function complete(Request $request, InstallationSchedule $schedule): JsonResponse
    {
        $data = $request->validate([
            'result_photo_urls' => ['nullable', 'array'],
        ]);

        try {
            $result = $this->service->complete($schedule, $data['result_photo_urls'] ?? [], $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error('INVALID_STATE', $e->getMessage(), null, 422);
        }

        return ApiResponse::message('Pemasangan selesai.', $result);
    }

    /** Hublang/Teknik: aktivasi pelanggan baru dari prospek terpasang. */
    public function activate(Request $request, CustomerProspect $prospect): JsonResponse
    {
        $data = $request->validate([
            'customer_number' => ['nullable', 'string', 'max:30'],
            'tariff_category_id' => ['nullable', 'integer'],
            'meter_serial_number' => ['nullable', 'string', 'max:50'],
            'meter_route_id' => ['nullable', 'integer'],
        ]);

        try {
            $customer = $this->service->activate($prospect, $data);
        } catch (RuntimeException $e) {
            return ApiResponse::error('INVALID_STATE', $e->getMessage(), null, 422);
        }

        return ApiResponse::message('Pelanggan diaktifkan.', $customer, 201);
    }
}
