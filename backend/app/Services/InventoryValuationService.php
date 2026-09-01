<?php

namespace App\Services;

use App\Models\Material;
use App\Models\MaterialStock;

/**
 * InventoryValuationService — valuasi persediaan FIFO/Average (PRD 8.6).
 *
 * Skema aktual: stok fisik disimpan di `material_stocks.current_stock`, harga
 * acuan di `materials.last_price`. Transaksi mutasi (material_transactions)
 * tidak menyimpan harga, sehingga valuasi memakai harga bahan terakhir
 * (last_price) sebagai rata-rata biaya sederhana untuk demo.
 */
class InventoryValuationService
{
    public function fifoValue(int $materialId, int $warehouseId): array
    {
        return $this->valuate($materialId, $warehouseId, 'FIFO');
    }

    public function averageValue(int $materialId, int $warehouseId): array
    {
        return $this->valuate($materialId, $warehouseId, 'AVERAGE');
    }

    protected function valuate(int $materialId, int $warehouseId, string $method): array
    {
        $material = Material::find($materialId);
        $stock = MaterialStock::where('material_id', $materialId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        $currentQty = $stock ? (float) $stock->current_stock : 0;
        $cost = $material ? (float) $material->last_price : 0;
        $totalValue = $currentQty * $cost;

        return [
            'material_id' => $materialId,
            'warehouse_id' => $warehouseId,
            'quantity' => $currentQty,
            'method' => $method,
            'total_value' => round($totalValue, 2),
            'avg_unit_cost' => round($cost, 2),
            'total_purchases_qty' => $currentQty,
            'total_purchases_cost' => round($totalValue, 2),
        ];
    }

    public function inventoryBalanceSheet(int $orgId): array
    {
        $stocks = MaterialStock::with('material')
            ->where('pdam_org_id', $orgId)
            ->where('current_stock', '>', 0)
            ->get();

        $totalValue = 0;
        $items = [];

        foreach ($stocks as $stock) {
            $val = $this->averageValue($stock->material_id, $stock->warehouse_id);
            $totalValue += $val['total_value'];

            $items[] = [
                'material' => $stock->material?->name,
                'warehouse_id' => $stock->warehouse_id,
                'quantity' => (float) $stock->current_stock,
                'unit' => $stock->material?->unit,
                'avg_cost' => $val['avg_unit_cost'],
                'total_value' => $val['total_value'],
            ];
        }

        return [
            'total_inventory_value' => round($totalValue, 2),
            'item_count' => count($items),
            'items' => $items,
        ];
    }
}
