<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Chemical;
use App\Models\ChemicalQcTest;
use App\Models\ChemicalReceipt;
use App\Models\ChemicalReceiptItem;
use App\Models\ChemicalStock;
use App\Models\ChemicalUsage;
use App\Services\ChemicalStockService;
use App\Services\JournalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChemController extends Controller
{
    public function __construct(
        private ChemicalStockService $chemStock,
        private JournalService $journal,
    ) {}

    public function chemicals(Request $request): JsonResponse
    {
        $query = Chemical::when($request->input('is_active'), fn ($q, $v) => $q->where('is_active', $v));

        return ApiResponse::paginated($query->paginate($request->input('per_page', 25)));
    }

    public function storeChemical(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:chemicals,code'],
            'name' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:20'],
            'standard_dosage' => ['nullable', 'numeric'],
            'safety_threshold' => ['nullable', 'numeric'],
            'msds_url' => ['nullable', 'string'],
        ]);

        return ApiResponse::success(Chemical::create($data), status: 201);
    }

    public function receipts(Request $request): JsonResponse
    {
        $query = ChemicalReceipt::with('items.chemical')->orderByDesc('receipt_date');

        return ApiResponse::paginated($query->paginate($request->input('per_page', 25)));
    }

    public function createReceipt(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'integer'],
            'receipt_date' => ['required', 'date'],
            'batch_number' => ['nullable', 'string', 'max:50'],
            'expiry_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.chemical_id' => ['required', 'integer', 'exists:chemicals,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['required', 'string', 'max:20'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $receipt = ChemicalReceipt::create([
            'receipt_number' => 'CRM-'.now()->format('ym').'-'.strtoupper(uniqid()),
            'supplier_id' => $data['supplier_id'] ?? null,
            'receipt_date' => $data['receipt_date'],
            'batch_number' => $data['batch_number'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'status' => 'pending_qc',
        ]);

        foreach ($data['items'] as $item) {
            ChemicalReceiptItem::create([
                'pdam_org_id' => $receipt->pdam_org_id,
                'chemical_receipt_id' => $receipt->id,
                'chemical_id' => $item['chemical_id'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'unit_cost' => $item['unit_cost'] ?? null,
            ]);
        }

        return ApiResponse::success($receipt->load('items'), status: 201);
    }

    public function qcTest(Request $request, ChemicalReceipt $receipt): JsonResponse
    {
        $data = $request->validate([
            'tests' => ['required', 'array', 'min:1'],
            'tests.*.test_name' => ['required', 'string', 'max:100'],
            'tests.*.result_value' => ['nullable', 'numeric'],
            'tests.*.result_unit' => ['nullable', 'string', 'max:20'],
            'tests.*.verdict' => ['required', 'in:passed,failed'],
        ]);

        $allPassed = true;
        foreach ($data['tests'] as $test) {
            ChemicalQcTest::create([
                'pdam_org_id' => $receipt->pdam_org_id,
                'chemical_receipt_id' => $receipt->id,
                'test_name' => $test['test_name'],
                'result_value' => $test['result_value'] ?? null,
                'result_unit' => $test['result_unit'] ?? null,
                'verdict' => $test['verdict'],
                'tested_by' => $request->user()->id,
            ]);
            if ($test['verdict'] !== 'passed') {
                $allPassed = false;
            }
        }

        $receipt->update(['status' => $allPassed ? 'passed_qc' : 'rejected']);

        if ($allPassed) {
            $this->chemStock->receive($receipt);
        }

        return ApiResponse::success(['status' => $receipt->status]);
    }

    public function usage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chemical_id' => ['required', 'integer', 'exists:chemicals,id'],
            'usage_date' => ['required', 'date'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit' => ['required', 'string', 'max:20'],
            'water_produced_m3' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->chemStock->issue(
            $data['chemical_id'],
            $data['quantity'],
            'chemical_usage',
            0
        );

        $usage = ChemicalUsage::create([
            'pdam_org_id' => $request->user()->pdam_org_id,
            'chemical_id' => $data['chemical_id'],
            'usage_date' => $data['usage_date'],
            'quantity' => $data['quantity'],
            'unit' => $data['unit'],
            'water_produced_m3' => $data['water_produced_m3'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);

        $chemical = Chemical::find($data['chemical_id']);
        $stock = ChemicalStock::where('chemical_id', $data['chemical_id'])
            ->where('quantity', '>', 0)
            ->sum('quantity');

        return ApiResponse::success([
            'usage' => $usage,
            'remaining_stock' => (float) $stock,
            'dosage_per_m3' => $data['water_produced_m3'] ? round($data['quantity'] / $data['water_produced_m3'] * 1000, 4) : null,
        ], status: 201);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $expiring = ChemicalStock::where('expiry_date', '<=', now()->addDays(30))
            ->where('expiry_date', '>', now())
            ->where('quantity', '>', 0)
            ->with('chemical')
            ->get()
            ->map(fn ($s) => [
                'chemical' => $s->chemical->name,
                'batch' => $s->batch_number,
                'quantity' => (float) $s->quantity,
                'expiry_date' => $s->expiry_date->toDateString(),
                'days_left' => now()->diffInDays($s->expiry_date),
            ]);

        $usageThisMonth = ChemicalUsage::whereMonth('usage_date', now()->month)
            ->whereYear('usage_date', now()->year)
            ->with('chemical')
            ->get()
            ->groupBy('chemical.name')
            ->map(fn ($items) => $items->sum('quantity'));

        $stocks = ChemicalStock::where('quantity', '>', 0)
            ->with('chemical')
            ->get()
            ->groupBy('chemical.name')
            ->map(fn ($items) => $items->sum('quantity'));

        return ApiResponse::success([
            'expiring_soon' => $expiring,
            'usage_this_month' => $usageThisMonth,
            'current_stocks' => $stocks,
            'total_chemicals' => Chemical::count(),
            'pending_qc' => ChemicalReceipt::where('status', 'pending_qc')->count(),
        ]);
    }
}
