<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Currency;
use App\Models\Project;
use App\Models\RecurringTransaction;
use App\Models\SalesInvoice;
use App\Models\TaxRecord;
use App\Services\JournalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinController extends Controller
{
    public function __construct(private JournalService $journal) {}

    // ── AR: Sales Invoices ─────────────────────────────────────────────────
    public function salesInvoices(Request $request): JsonResponse
    {
        $query = SalesInvoice::when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('customer_id'), fn ($q, $v) => $q->where('customer_id', $v))
            ->orderByDesc('invoice_date');

        return ApiResponse::paginated($query->paginate($request->input('per_page', 25)));
    }

    public function createSalesInvoice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.account_code' => ['nullable', 'string', 'max:20'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $subtotal = 0;
        foreach ($data['items'] as $item) {
            $subtotal += $item['quantity'] * $item['unit_price'];
        }
        $taxRate = ($data['tax_percent'] ?? 0) / 100;
        $taxAmount = $subtotal * $taxRate;
        $total = $subtotal + $taxAmount;

        $invoice = SalesInvoice::create([
            'invoice_number' => 'INV-'.now()->format('ym').'-'.strtoupper(uniqid()),
            'customer_id' => $data['customer_id'] ?? null,
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'],
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'status' => 'unpaid',
            'notes' => $data['notes'] ?? null,
        ]);

        foreach ($data['items'] as $item) {
            $invoice->items()->create([
                'pdam_org_id' => $invoice->pdam_org_id,
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'amount' => $item['quantity'] * $item['unit_price'],
                'account_code' => $item['account_code'] ?? null,
            ]);
        }

        return ApiResponse::success($invoice->load('items'), status: 201);
    }

    public function arAgingReport(Request $request): JsonResponse
    {
        $now = now();

        $current = SalesInvoice::where('status', 'unpaid')->where('due_date', '>=', $now)->sum('total');
        $aging1to30 = SalesInvoice::where('status', 'unpaid')->where('due_date', '<', $now)->where('due_date', '>=', $now->copy()->subDays(30))->sum('total');
        $aging31to60 = SalesInvoice::where('status', 'unpaid')->where('due_date', '<', $now->copy()->subDays(30))->where('due_date', '>=', $now->copy()->subDays(60))->sum('total');
        $aging61to90 = SalesInvoice::where('status', 'unpaid')->where('due_date', '<', $now->copy()->subDays(60))->where('due_date', '>=', $now->copy()->subDays(90))->sum('total');
        $agingOver90 = SalesInvoice::where('status', 'unpaid')->where('due_date', '<', $now->copy()->subDays(90))->sum('total');

        return ApiResponse::success([
            'current' => (float) $current,
            '1_30_days' => (float) $aging1to30,
            '31_60_days' => (float) $aging31to60,
            '61_90_days' => (float) $aging61to90,
            'over_90_days' => (float) $agingOver90,
            'total_outstanding' => (float) ($current + $aging1to30 + $aging31to60 + $aging61to90 + $agingOver90),
        ]);
    }

    // ── Budget ─────────────────────────────────────────────────────────────
    public function budgets(Request $request): JsonResponse
    {
        $query = Budget::with('lines')
            ->when($request->input('fiscal_year'), fn ($q, $v) => $q->where('fiscal_year', $v))
            ->orderByDesc('fiscal_year');

        return ApiResponse::paginated($query->paginate($request->input('per_page', 25)));
    }

    public function createBudget(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2099'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_code' => ['required', 'string', 'max:20'],
            'lines.*.project_code' => ['nullable', 'string', 'max:30'],
            'lines.*.cost_center_code' => ['nullable', 'string', 'max:20'],
            'lines.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $budget = Budget::create(['name' => $data['name'], 'fiscal_year' => $data['fiscal_year'], 'total_amount' => $data['total_amount'], 'status' => 'draft']);

        foreach ($data['lines'] as $line) {
            BudgetLine::create(['pdam_org_id' => $budget->pdam_org_id, 'budget_id' => $budget->id, ...$line]);
        }

        return ApiResponse::success($budget->load('lines'), status: 201);
    }

    // ── Projects & Cost Centers ────────────────────────────────────────────
    public function projects(Request $request): JsonResponse
    {
        $query = Project::when($request->input('status'), fn ($q, $v) => $q->where('status', $v))->orderBy('code');

        return ApiResponse::paginated($query->paginate($request->input('per_page', 25)));
    }

    public function createProject(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:projects,code'],
            'name' => ['required', 'string', 'max:200'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
        ]);

        return ApiResponse::success(Project::create($data), status: 201);
    }

    // ── Tax ─────────────────────────────────────────────────────────────────
    public function taxRecords(Request $request): JsonResponse
    {
        $query = TaxRecord::when($request->input('tax_type'), fn ($q, $v) => $q->where('tax_type', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('tax_date');

        return ApiResponse::paginated($query->paginate($request->input('per_page', 25)));
    }

    // ── Bank ────────────────────────────────────────────────────────────────
    public function bankAccounts(Request $request): JsonResponse
    {
        $query = BankAccount::when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        return ApiResponse::paginated($query->paginate($request->input('per_page', 25)));
    }

    public function createBankAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'account_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:30'],
            'bank_name' => ['required', 'string', 'max:50'],
            'currency' => ['nullable', 'string', 'max:5'],
            'opening_balance' => ['nullable', 'numeric'],
            'coa_account_code' => ['nullable', 'string', 'max:20'],
        ]);

        $data['current_balance'] = $data['opening_balance'] ?? 0;

        return ApiResponse::success(BankAccount::create($data), status: 201);
    }

    // ── Currencies ──────────────────────────────────────────────────────────
    public function currencies(Request $request): JsonResponse
    {
        return ApiResponse::success(Currency::where('is_active', true)->get());
    }

    // ── Recurring ───────────────────────────────────────────────────────────
    public function recurringTransactions(Request $request): JsonResponse
    {
        return ApiResponse::success(RecurringTransaction::where('is_active', true)->get());
    }

    public function createRecurring(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'frequency' => ['required', 'in:daily,weekly,monthly,quarterly,annual'],
            'amount' => ['required', 'numeric', 'min:0'],
            'journal_lines' => ['required', 'array', 'min:2'],
            'journal_lines.*.account_code' => ['required', 'string', 'max:20'],
            'journal_lines.*.type' => ['required', 'in:DEBIT,KREDIT'],
            'journal_lines.*.amount' => ['required', 'numeric', 'min:0'],
            'next_run_date' => ['required', 'date'],
        ]);

        $recurring = RecurringTransaction::create($data);

        return ApiResponse::success($recurring, status: 201);
    }
}
