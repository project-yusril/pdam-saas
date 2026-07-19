<?php

namespace App\Services;

use App\Models\ChemicalReceipt;
use App\Models\ChemicalStock;
use App\Models\ChemicalTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ChemicalStockService — FEFO (First-Expired-First-Out) + batch management.
 * PRD 13: kelola stok bahan kimia per batch + expired.
 */
class ChemicalStockService
{
    public function receive(ChemicalReceipt $receipt): void
    {
        DB::transaction(function () use ($receipt) {
            $receipt->load('items.chemical');

            foreach ($receipt->items as $item) {
                ChemicalStock::create([
                    'pdam_org_id' => $receipt->pdam_org_id,
                    'chemical_id' => $item->chemical_id,
                    'warehouse_id' => $receipt->warehouse_id ?? null,
                    'batch_number' => $receipt->batch_number,
                    'expiry_date' => $receipt->expiry_date,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_cost' => $item->unit_cost,
                ]);
            }

            $receipt->update(['status' => 'received']);
        });
    }

    /**
     * Keluarkan stok dengan FEFO: batch expired paling dekat duluan.
     */
    public function issue(int $chemicalId, float $quantity, string $referenceType, int $referenceId): void
    {
        DB::transaction(function () use ($chemicalId, $quantity, $referenceType, $referenceId) {
            $remaining = $quantity;

            $batches = ChemicalStock::where('chemical_id', $chemicalId)
                ->where('quantity', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('expiry_date')
                        ->orWhere('expiry_date', '>', now());
                })
                ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expiry_date', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($remaining, $batch->quantity);
                $batch->decrement('quantity', $take);

                ChemicalTransaction::create([
                    'pdam_org_id' => $batch->pdam_org_id,
                    'chemical_stock_id' => $batch->id,
                    'type' => 'out',
                    'quantity' => $take,
                    'balance_after' => $batch->quantity,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                ]);

                $remaining -= $take;
            }

            if ($remaining > 0) {
                throw new \RuntimeException("Stok tidak mencukupi: kurang {$remaining} unit.");
            }
        });
    }

    public function addStock(ChemicalStock $stock, float $quantity): void
    {
        $stock->increment('quantity', $quantity);

        ChemicalTransaction::create([
            'pdam_org_id' => $stock->pdam_org_id,
            'chemical_stock_id' => $stock->id,
            'type' => 'in',
            'quantity' => $quantity,
            'balance_after' => $stock->fresh()->quantity,
        ]);
    }

    public function getExpiringBatches(int $daysThreshold = 30): Collection
    {
        return ChemicalStock::where('quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($daysThreshold))
            ->with('chemical')
            ->get();
    }
}
