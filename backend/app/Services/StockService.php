<?php

namespace App\Services;

use App\Models\MaterialStock;
use App\Models\MaterialTransaction;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * StockService — mutasi stok atomik per gudang (PRD 3.3, Fase 5).
 * Semua penambahan/pengurangan stok WAJIB lewat sini agar tercatat di
 * material_transactions (audit) dan tidak pernah minus.
 * Gudang Utama = sumber kebenaran; buffer hanya titipan.
 */
class StockService
{
    /** Tambah stok ke gudang + catat transaksi. */
    public function stockIn(int $materialId, int $warehouseId, float $qty, string $refType, ?int $refId = null, ?int $userId = null): MaterialStock
    {
        return $this->move($materialId, $warehouseId, $qty, 'stock_in', $refType, $refId, $userId);
    }

    /** Kurangi stok dari gudang; tolak jika tidak cukup. */
    public function stockOut(int $materialId, int $warehouseId, float $qty, string $refType, ?int $refId = null, ?int $userId = null): MaterialStock
    {
        return $this->move($materialId, $warehouseId, -$qty, 'stock_out', $refType, $refId, $userId);
    }

    /**
     * Transfer stok antar gudang (out dari asal, in ke tujuan) dalam 1 transaksi.
     * quantityReceived boleh < quantityRequested (kerusakan di jalan → selisih
     * tetap keluar dari asal untuk audit, hanya yang diterima yang masuk tujuan).
     */
    public function transfer(int $materialId, int $fromWarehouseId, int $toWarehouseId, float $qtyRequested, ?float $qtyReceived, int $refId, ?int $userId = null): void
    {
        $received = $qtyReceived ?? $qtyRequested;

        DB::transaction(function () use ($materialId, $fromWarehouseId, $toWarehouseId, $qtyRequested, $received, $refId, $userId) {
            $this->stockOut($materialId, $fromWarehouseId, $qtyRequested, 'stock_transfer', $refId, $userId);
            $this->stockIn($materialId, $toWarehouseId, $received, 'stock_transfer', $refId, $userId);
        });
    }

    protected function move(int $materialId, int $warehouseId, float $delta, string $txType, string $refType, ?int $refId, ?int $userId): MaterialStock
    {
        return DB::transaction(function () use ($materialId, $warehouseId, $delta, $txType, $refType, $refId, $userId) {
            $stock = MaterialStock::where('material_id', $materialId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                $stock = MaterialStock::create([
                    'pdam_org_id' => TenantContext::id(),
                    'material_id' => $materialId,
                    'warehouse_id' => $warehouseId,
                    'current_stock' => 0,
                    'minimum_stock' => 0,
                ]);
            }

            $newStock = (float) $stock->current_stock + $delta;
            if ($newStock < 0) {
                throw new RuntimeException("Stok tidak mencukupi di gudang #{$warehouseId} (butuh ".abs($delta).", tersedia {$stock->current_stock}).");
            }

            $stock->update(['current_stock' => $newStock]);

            MaterialTransaction::create([
                'pdam_org_id' => TenantContext::id(),
                'material_id' => $materialId,
                'warehouse_id' => $warehouseId,
                'transaction_type' => $txType,
                'quantity' => abs($delta),
                'balance_after' => $newStock,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'created_by' => $userId,
            ]);

            return $stock->fresh();
        });
    }
}
