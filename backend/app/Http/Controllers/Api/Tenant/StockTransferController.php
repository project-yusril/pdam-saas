<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\StockTransfer;
use App\Services\StockService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * StockTransferController — transfer stok antar gudang (PRD 3.3 Tahap 4-5, Fase 5).
 * main_to_buffer (rutin) & buffer_to_buffer (lateral, situasi khusus).
 * Stok baru berpindah saat status "received" dikonfirmasi staf wilayah tujuan.
 */
class StockTransferController extends Controller
{
    public function __construct(private StockService $stock) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);
        $query = StockTransfer::with('items')->orderByDesc('created_at');
        if ($type = $request->query('transfer_type')) {
            $query->where('transfer_type', $type);
        }

        return ApiResponse::paginated($query->paginate($perPage));
    }

    /** Kepala Gudang: buat transfer order (draft). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'transfer_type' => ['required', 'in:main_to_buffer,buffer_to_buffer'],
            'from_warehouse_id' => ['required', 'integer', 'different:to_warehouse_id'],
            'to_warehouse_id' => ['required', 'integer'],
            'reason' => ['nullable', 'in:urgent_installation,stock_imbalance,closer_location,other'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'integer'],
            'items.*.quantity_requested' => ['required', 'numeric', 'min:0.01'],
        ]);

        // Lateral transfer wajib menyertakan alasan (PRD 3.3 Tahap 5)
        if ($data['transfer_type'] === 'buffer_to_buffer' && empty($data['reason'])) {
            return ApiResponse::error('REASON_REQUIRED', 'Lateral transfer wajib menyertakan alasan.', null, 422);
        }

        $transfer = DB::transaction(function () use ($data, $request) {
            $transfer = StockTransfer::create([
                'transfer_number' => 'TRF-'.strtoupper(Str::random(8)),
                'transfer_type' => $data['transfer_type'],
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'requested_by' => $request->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                $transfer->items()->create([
                    'pdam_org_id' => $transfer->pdam_org_id,
                    'material_id' => $item['material_id'],
                    'quantity_requested' => $item['quantity_requested'],
                ]);
            }

            return $transfer->load('items');
        });

        return ApiResponse::message('Transfer order dibuat.', $transfer, 201);
    }

    /** Kepala Gudang: setujui & kirim → status in_transit. */
    public function approve(Request $request, StockTransfer $transfer): JsonResponse
    {
        if ($transfer->status !== 'draft') {
            return ApiResponse::error('INVALID_STATE', 'Transfer sudah diproses.', null, 422);
        }

        $transfer->update([
            'status' => 'in_transit',
            'approved_by' => $request->user()->id,
        ]);

        return ApiResponse::message('Transfer disetujui & dikirim.', $transfer->fresh('items'));
    }

    /**
     * Staf wilayah tujuan: konfirmasi diterima → stok berpindah.
     * quantity_received per item boleh < requested (kerusakan di jalan).
     */
    public function receive(Request $request, StockTransfer $transfer): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.quantity_received' => ['required', 'numeric', 'min:0'],
        ]);

        if ($transfer->status !== 'in_transit') {
            return ApiResponse::error('INVALID_STATE', 'Transfer belum dalam status dikirim.', null, 422);
        }

        $receivedMap = collect($data['items'])->keyBy('item_id');

        DB::transaction(function () use ($transfer, $receivedMap, $request) {
            foreach ($transfer->items as $item) {
                $received = (float) ($receivedMap[$item->id]['quantity_received'] ?? $item->quantity_requested);
                $item->update(['quantity_received' => $received]);

                $this->stock->transfer(
                    $item->material_id,
                    $transfer->from_warehouse_id,
                    $transfer->to_warehouse_id,
                    (float) $item->quantity_requested,
                    $received,
                    $transfer->id,
                    $request->user()->id,
                );
            }

            $transfer->update([
                'status' => 'completed',
                'received_by' => $request->user()->id,
                'completed_at' => now(),
            ]);
        });

        return ApiResponse::message('Transfer diterima, stok diperbarui.', $transfer->fresh('items'));
    }
}
