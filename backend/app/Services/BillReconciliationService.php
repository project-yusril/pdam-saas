<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillAdjustment;
use App\Models\Payment;
use App\Support\TenantContext;

class BillReconciliationService
{
    public function reconcilePeriod(string $period): array
    {
        $orgId = TenantContext::id();

        $bills = Bill::where('pdam_org_id', $orgId)
            ->where('period', $period)
            ->get();

        $totalBilled = $bills->sum('amount_due');
        $totalPaid = Payment::whereIn('bill_id', $bills->pluck('id'))
            ->where('status', 'success')
            ->sum('amount');

        $adjustments = BillAdjustment::whereHas('bill', fn ($q) => $q->where('period', $period))
            ->where('status', 'posted')
            ->sum('adjustment_amount');

        $expectedOutstanding = $totalBilled + $adjustments - $totalPaid;

        $actualOutstanding = $bills->whereIn('status', ['unpaid', 'overdue'])->sum('amount_due');

        $difference = round($expectedOutstanding - $actualOutstanding, 2);

        return [
            'period' => $period,
            'total_billed' => round($totalBilled, 2),
            'total_paid' => round($totalPaid, 2),
            'total_adjustments' => round($adjustments, 2),
            'expected_outstanding' => round($expectedOutstanding, 2),
            'actual_outstanding' => round($actualOutstanding, 2),
            'difference' => $difference,
            'status' => abs($difference) < 1 ? 'balanced' : 'unbalanced',
            'bill_count' => $bills->count(),
            'paid_count' => $bills->where('status', 'paid')->count(),
            'unpaid_count' => $bills->whereIn('status', ['unpaid', 'overdue'])->count(),
        ];
    }

    public function auditLog(int $billId): array
    {
        $adjustments = BillAdjustment::where('bill_id', $billId)
            ->with(['requestedBy:id,name', 'approvedBy:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'old_amount' => (float) $a->old_amount,
                'new_amount' => (float) $a->new_amount,
                'adjustment' => (float) $a->adjustment_amount,
                'requested_by' => $a->requestedBy?->name,
                'approved_by' => $a->approvedBy?->name,
                'status' => $a->status,
                'reason' => $a->reason,
                'created_at' => $a->created_at->toIso8601String(),
            ]);

        return ['bill_id' => $billId, 'adjustments' => $adjustments, 'total_changes' => $adjustments->count()];
    }
}
