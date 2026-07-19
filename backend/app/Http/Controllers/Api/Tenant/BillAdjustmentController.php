<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillAdjustment;
use App\Services\JournalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillAdjustmentController extends Controller
{
    public function __construct(private JournalService $journal) {}

    public function index(Request $request): JsonResponse
    {
        $query = BillAdjustment::with('bill')
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('created_at');

        $paginator = $query->paginate($request->input('per_page', 25));

        return ApiResponse::paginated($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bill_id' => ['required', 'integer', 'exists:bills,id'],
            'type' => ['required', 'in:correction,waiver,manual_penalty,batch'],
            'reason' => ['required', 'string', 'max:500'],
            'new_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $bill = Bill::findOrFail($data['bill_id']);
        $oldAmount = $bill->amount_due;
        $newAmount = $data['new_amount'];

        $adjustment = BillAdjustment::create([
            'bill_id' => $bill->id,
            'type' => $data['type'],
            'reason' => $data['reason'],
            'old_amount' => $oldAmount,
            'new_amount' => $newAmount,
            'adjustment_amount' => $newAmount - $oldAmount,
            'requested_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        return ApiResponse::success($adjustment, status: 201);
    }

    public function approve(Request $request, BillAdjustment $adjustment): JsonResponse
    {
        if ($adjustment->status !== 'pending') {
            return ApiResponse::error('INVALID_STATE', 'Adjustment tidak dalam status pending.', null, 422);
        }

        $adjustment->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
        ]);

        $bill = $adjustment->bill;
        $diff = $adjustment->new_amount - $adjustment->old_amount;

        if ($diff != 0 && in_array($adjustment->type, ['correction', 'waiver'])) {
            $entry = $this->journal->record(
                "Adjustment tagihan {$bill->bill_number} — {$adjustment->type}",
                [
                    ['account_code' => '1-002', 'type' => $diff < 0 ? 'KREDIT' : 'DEBIT', 'amount' => abs($diff), 'memo' => 'Koreksi piutang'],
                    ['account_code' => '4-001', 'type' => $diff < 0 ? 'DEBIT' : 'KREDIT', 'amount' => abs($diff), 'memo' => 'Koreksi pendapatan'],
                ],
                'BILL_ADJUSTMENT',
                $adjustment->id,
            );
            $adjustment->update(['journal_entry_id' => $entry->id]);
        }

        $bill->update(['amount_due' => $adjustment->new_amount]);

        return ApiResponse::success($adjustment->fresh());
    }

    public function reject(Request $request, BillAdjustment $adjustment): JsonResponse
    {
        if ($adjustment->status !== 'pending') {
            return ApiResponse::error('INVALID_STATE', 'Adjustment tidak dalam status pending.', null, 422);
        }

        $adjustment->update(['status' => 'rejected', 'approved_by' => $request->user()->id]);

        return ApiResponse::success($adjustment);
    }

    public function post(Request $request, BillAdjustment $adjustment): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        if ($adjustment->status !== 'approved') {
            return ApiResponse::error('INVALID_STATE', 'Adjustment belum di-approve.', null, 422);
        }

        $adjustment->update(['status' => 'posted', 'notes' => $data['notes'] ?? $adjustment->notes]);

        return ApiResponse::success($adjustment);
    }
}
