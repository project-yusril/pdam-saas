<?php

namespace App\Services;

use App\Models\MaterialStock;
use App\Models\MaterialTransaction;

/**
 * InventoryValuationService — valuasi persediaan FIFO/Average (PRD 8.6).
 */
class InventoryValuationService
{
    public function fifoValue(int $materialId, int $warehouseId): array
    {
        $transactions = MaterialTransaction::whereHas('stock', function ($q) use ($materialId, $warehouseId) {
            $q->where('material_id', $materialId)->where('warehouse_id', $warehouseId);
        })
            ->where('type', 'in')
            ->orderBy('created_at')
            ->get();

        $stock = MaterialStock::where('material_id', $materialId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        $currentQty = $stock ? (float) $stock->quantity : 0;
        $remaining = $currentQty;
        $totalValue = 0;

        foreach ($transactions as $tx) {
            if ($remaining <= 0) {
                break;
            }
            $taken = min($remaining, (float) $tx->quantity);
            $price = $tx->reference_price ?? 0;
            $totalValue += $taken * $price;
            $remaining -= $taken;
        }

        return [
            'material_id' => $materialId,
            'warehouse_id' => $warehouseId,
            'quantity' => $currentQty,
            'method' => 'FIFO',
            'total_value' => round($totalValue, 2),
            'avg_unit_cost' => $currentQty > 0 ? round($totalValue / $currentQty, 2) : 0,
        ];
    }

    public function averageValue(int $materialId, int $warehouseId): array
    {
        $stock = MaterialStock::where('material_id', $materialId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        $currentQty = $stock ? (float) $stock->quantity : 0;

        $totalIn = MaterialTransaction::whereHas('stock', function ($q) use ($materialId, $warehouseId) {
            $q->where('material_id', $materialId)->where('warehouse_id', $warehouseId);
        })
            ->where('type', 'in')
            ->sum('quantity');

        $totalCost = MaterialTransaction::whereHas('stock', function ($q) use ($materialId, $warehouseId) {
            $q->where('material_id', $materialId)->where('warehouse_id', $warehouseId);
        })
            ->where('type', 'in')
            ->get()
            ->sum(function ($tx) {
                return (float) ($tx->reference_price ?? 0) * (float) $tx->quantity;
            });

        $avgCost = $totalIn > 0 ? $totalCost / $totalIn : 0;
        $totalValue = $currentQty * $avgCost;

        return [
            'material_id' => $materialId,
            'warehouse_id' => $warehouseId,
            'quantity' => $currentQty,
            'method' => 'AVERAGE',
            'total_value' => round($totalValue, 2),
            'avg_unit_cost' => round($avgCost, 2),
            'total_purchases_qty' => (float) $totalIn,
            'total_purchases_cost' => round($totalCost, 2),
        ];
    }

    public function inventoryBalanceSheet(int $orgId): array
    {
        $stocks = MaterialStock::with('material')
            ->where('pdam_org_id', $orgId)
            ->where('quantity', '>', 0)
            ->get();

        $totalValue = 0;
        $items = [];

        foreach ($stocks as $stock) {
            $val = $this->averageValue($stock->material_id, $stock->warehouse_id);
            $totalValue += $val['total_value'];

            $items[] = [
                'material' => $stock->material->name,
                'warehouse_id' => $stock->warehouse_id,
                'quantity' => (float) $stock->quantity,
                'unit' => $stock->material->unit,
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
