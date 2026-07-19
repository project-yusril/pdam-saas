<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\MaterialStock;
use App\Models\MaterialTransaction;
use App\Models\StockAdjustment;
use App\Services\JournalService;
use App\Services\StockService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function __construct(
        private StockService $stock,
        private JournalService $journal,
    ) {}

    public function stockOut(Request $request): JsonResponse
    {
        $data = $request->validate([
            'material_id' => ['required', 'integer', 'exists:materials,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'purpose' => ['required', 'in:installation,repair,transfer,sold'],
            'reference_type' => ['nullable', 'string'],
            'reference_id' => ['nullable', 'integer'],
            'zone_id' => ['nullable', 'integer'],
        ]);

        $stock = MaterialStock::where('material_id', $data['material_id'])
            ->where('warehouse_id', $data['warehouse_id'])
            ->lockForUpdate()
            ->first();

        if (! $stock || $stock->quantity < $data['quantity']) {
            return ApiResponse::error('INSUFFICIENT_STOCK', 'Stok tidak mencukupi.', null, 422);
        }

        $before = $stock->quantity;
        $stock->decrement('quantity', $data['quantity']);

        MaterialTransaction::create([
            'pdam_org_id' => $stock->pdam_org_id,
            'material_stock_id' => $stock->id,
            'type' => 'out',
            'quantity' => $data['quantity'],
            'balance_after' => $stock->fresh()->quantity,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'notes' => $data['purpose'],
        ]);

        if ($data['purpose'] === 'installation') {
            $this->journal->record(
                'Pemakaian material untuk pemasangan',
                [
                    ['account_code' => '1-004', 'type' => 'DEBIT', 'amount' => $stock->material->unit_price * $data['quantity'], 'memo' => 'Kapitalisasi aset jaringan'],
                    ['account_code' => '1-003', 'type' => 'KREDIT', 'amount' => $stock->material->unit_price * $data['quantity'], 'memo' => 'Pengurangan persediaan'],
                ],
                'material_issue',
                $stock->id,
            );
        } elseif ($data['purpose'] === 'repair') {
            $amount = ($stock->material->unit_price ?? 0) * $data['quantity'];
            if ($amount > 0) {
                $this->journal->record(
                    'Pemakaian material untuk perbaikan',
                    [
                        ['account_code' => '5-201', 'type' => 'DEBIT', 'amount' => $amount, 'memo' => 'Beban perbaikan'],
                        ['account_code' => '1-003', 'type' => 'KREDIT', 'amount' => $amount, 'memo' => 'Pengurangan persediaan'],
                    ],
                    'material_issue',
                    $stock->id,
                );
            }
        }

        return ApiResponse::success([
            'material_id' => $stock->material_id,
            'quantity_before' => $before,
            'quantity_after' => $stock->fresh()->quantity,
            'purpose' => $data['purpose'],
        ]);
    }

    public function stockDashboard(Request $request): JsonResponse
    {
        $warehouseId = $request->input('warehouse_id');
        $materialId = $request->input('material_id');

        $query = MaterialStock::with(['material:id,code,name,unit', 'warehouse:id,code,name'])
            ->when($warehouseId, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->when($materialId, fn ($q, $v) => $q->where('material_id', $v));

        $stocks = $query->get()->map(function ($s) {
            return [
                'id' => $s->id,
                'material_code' => $s->material->code,
                'material_name' => $s->material->name,
                'warehouse' => $s->warehouse->name,
                'quantity' => (float) $s->quantity,
                'unit' => $s->material->unit,
                'status' => $s->quantity <= 0 ? 'empty' : ($s->quantity < 10 ? 'low' : 'normal'),
            ];
        });

        $lowStock = $stocks->where('status', 'low')->values();
        $emptyStock = $stocks->where('status', 'empty')->values();

        return ApiResponse::success([
            'total_items' => $stocks->count(),
            'low_stock_count' => $lowStock->count(),
            'empty_stock_count' => $emptyStock->count(),
            'stocks' => $stocks,
            'low_stock' => $lowStock,
        ]);
    }

    public function stockAdjustment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'material_id' => ['required', 'integer', 'exists:materials,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'actual_quantity' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $stock = MaterialStock::where('material_id', $data['material_id'])
            ->where('warehouse_id', $data['warehouse_id'])
            ->lockForUpdate()
            ->firstOrFail();

        $systemQty = $stock->quantity;
        $diff = $data['actual_quantity'] - $systemQty;

        if ($diff == 0) {
            return ApiResponse::message('Stok sesuai, tidak ada penyesuaian.');
        }

        $adjustment = StockAdjustment::create([
            'pdam_org_id' => $stock->pdam_org_id,
            'material_stock_id' => $stock->id,
            'system_quantity' => $systemQty,
            'actual_quantity' => $data['actual_quantity'],
            'difference' => $diff,
            'reason' => $data['reason'],
            'checked_by' => $request->user()->id,
            'adjustment_date' => now()->toDateString(),
        ]);

        $stock->update(['quantity' => $data['actual_quantity']]);

        MaterialTransaction::create([
            'pdam_org_id' => $stock->pdam_org_id,
            'material_stock_id' => $stock->id,
            'type' => $diff > 0 ? 'in' : 'out',
            'quantity' => abs($diff),
            'balance_after' => $data['actual_quantity'],
            'notes' => "Stock opname: {$data['reason']}",
        ]);

        $amount = abs($diff) * ($stock->material->unit_price ?? 0);
        if ($amount > 0) {
            $this->journal->record(
                "Stock opname - {$stock->material->code}",
                [
                    ['account_code' => $diff > 0 ? '1-003' : '5-202', 'type' => $diff > 0 ? 'DEBIT' : 'DEBIT', 'amount' => $amount, 'memo' => $diff > 0 ? 'Tambahan persediaan' : 'Selisih kurang persediaan'],
                    ['account_code' => $diff > 0 ? '4-006' : '1-003', 'type' => 'KREDIT', 'amount' => $amount, 'memo' => $diff > 0 ? 'Pendapatan selisih stok' : 'Pengurangan persediaan'],
                ],
                'stock_adjustment',
                $adjustment->id,
            );
        }

        return ApiResponse::success([
            'adjustment' => $adjustment,
            'system_quantity' => $systemQty,
            'actual_quantity' => $data['actual_quantity'],
            'difference' => $diff,
        ]);
    }
}
