<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CustomerProspect;
use App\Models\InstallationSchedule;
use App\Services\InstallationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * InstallationController — penjadwalan & aktivasi pemasangan baru (PRD 3.1 tahap 6).
 * payment_paid → installation_scheduled → installed → active.
 */
class InstallationController extends Controller
{
    public function __construct(private InstallationService $service) {}

    /**
     * Kepala Teknik/Gudang: order material pemasangan untuk prospek.
     * Mengembalikan rencana material dan status eksplisit bahwa stok belum dimutasi.
     */
    public function orderMaterials(Request $request, CustomerProspect $prospect): JsonResponse
    {
        try {
            $result = $this->service->orderMaterials($prospect);
        } catch (RuntimeException $e) {
            return ApiResponse::error('INVALID_STATE', $e->getMessage(), null, 422);
        }

        $message = $result['warehouse_module_active']
            ? 'Rencana material tersedia. Reservasi, pengeluaran stok, dan jurnal gudang belum dilakukan.'
            : 'Rencana material tersedia tanpa modul gudang. Tidak ada reservasi, pengeluaran stok, atau jurnal.';

        return ApiResponse::message($message, $result);
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

    /** Teknisi: tandai pemasangan selesai. */
    public function complete(Request $request, InstallationSchedule $schedule): JsonResponse
    {
        $data = $request->validate([
            'result_photo_urls' => ['nullable', 'array'],
        ]);

        try {
            $result = $this->service->complete($schedule, $data['result_photo_urls'] ?? []);
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
