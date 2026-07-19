<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\TaxRecord;
use App\Services\BankReconciliationService;
use App\Services\TaxService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinAdvanceController extends Controller
{
    public function __construct(
        private TaxService $tax,
        private BankReconciliationService $bankRecon,
    ) {}

    public function taxCalculate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:ppn,pph21,pph23'],
            'amount' => ['required', 'numeric', 'min:0'],
            'tax_status' => ['required_if:type,pph21', 'in:TK,K'],
            'dependents' => ['nullable', 'integer', 'min:0', 'max:3'],
        ]);

        $result = match ($data['type']) {
            'ppn' => $this->tax->calculatePpn($data['amount']),
            'pph21' => $this->tax->calculatePph21($data['amount'], $data['tax_status'] ?? 'TK', $data['dependents'] ?? 0),
            'pph23' => ['tax' => $this->tax->calculatePph23($data['amount'], 'service')],
        };

        return ApiResponse::success($result);
    }

    public function taxRecord(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tax_type' => ['required', 'in:PPN,PPH21,PPH23,PPH4A2'],
            'reference_type' => ['nullable', 'string'],
            'reference_id' => ['nullable', 'integer'],
            'dpp' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
        ]);

        $record = $this->tax->record(
            $data['tax_type'],
            $data['reference_type'] ?? 'manual',
            $data['reference_id'] ?? 0,
            $data['dpp'],
            $data['tax_amount'],
            $data['due_date'] ?? null,
        );

        return ApiResponse::success($record, status: 201);
    }

    public function exportEfaktur(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $records = TaxRecord::where('tax_type', 'PPN')
            ->whereYear('tax_date', substr($data['month'], 0, 4))
            ->whereMonth('tax_date', substr($data['month'], 5, 2))
            ->get();

        $csv = $this->tax->exportEfakturCsv($records);

        return response()->json([
            'month' => $data['month'],
            'record_count' => $records->count(),
            'csv_content' => $csv,
        ]);
    }

    public function reconcileBank(Request $request, BankAccount $account): JsonResponse
    {
        $data = $request->validate([
            'statement_balance' => ['required', 'numeric'],
            'statement_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $result = $this->bankRecon->reconcile(
            $account,
            $data['statement_balance'],
            $data['statement_date'],
            $data['notes'] ?? null,
        );

        return ApiResponse::success($result, status: 201);
    }

    public function bankTransfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'to_account_id' => ['required', 'integer', 'different:from_account_id', 'exists:bank_accounts,id'],
            'amount' => ['required', 'numeric', 'min:1000'],
            'notes' => ['nullable', 'string'],
        ]);

        $from = BankAccount::findOrFail($data['from_account_id']);
        $to = BankAccount::findOrFail($data['to_account_id']);

        $result = $this->bankRecon->transfer($from, $to, $data['amount'], $data['notes'] ?? '');

        return ApiResponse::success($result);
    }

    public function bankSummary(BankAccount $account): JsonResponse
    {
        return ApiResponse::success($this->bankRecon->getReconciliationSummary($account));
    }

    public function reconciliations(Request $request): JsonResponse
    {
        $query = BankReconciliation::with('bankAccount:id,code,account_name')
            ->when($request->input('bank_account_id'), fn ($q, $v) => $q->where('bank_account_id', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('reconciliation_date');

        return ApiResponse::paginated($query->paginate($request->input('per_page', 25)));
    }
}
