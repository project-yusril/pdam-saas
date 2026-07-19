<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Services\InventoryValuationService;
use App\Services\JournalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        private InventoryValuationService $inventory,
        private JournalService $journal,
    ) {}

    public function valuation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'material_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'method' => ['in:fifo,average'],
        ]);

        $method = $data['method'] ?? 'average';
        $result = $method === 'fifo'
            ? $this->inventory->fifoValue($data['material_id'], $data['warehouse_id'])
            : $this->inventory->averageValue($data['material_id'], $data['warehouse_id']);

        return ApiResponse::success($result);
    }

    public function balanceSheet(Request $request): JsonResponse
    {
        $orgId = $request->user()->pdam_org_id;

        return ApiResponse::success($this->inventory->inventoryBalanceSheet($orgId));
    }

    public function manualJournal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string'],
            'date' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_code' => ['required', 'string', 'max:20'],
            'lines.*.type' => ['required', 'in:DEBIT,KREDIT'],
            'lines.*.amount' => ['required', 'numeric', 'min:0.01'],
            'lines.*.memo' => ['nullable', 'string'],
        ]);

        $entry = $this->journal->record(
            $data['description'],
            $data['lines'],
            'manual_journal',
            $request->user()->id,
            $data['date'],
        );

        return ApiResponse::success($entry, status: 201);
    }
}
