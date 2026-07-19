<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\InstallmentPlan;
use App\Models\InstallmentSchedule;
use App\Support\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * InstallmentService — cicilan tunggakan (PRD 10.4).
 *
 * Alur: pelanggan menunggak beberapa bulan → dibuat rencana cicilan atas
 * kumpulan tagihan tunggakan → approval berjenjang (staf → direktur) →
 * saat approved, jadwal termin dibuat & tagihan asli ditandai "waived"
 * (dipindah ke skema cicilan). Pembayaran termin memicu jurnal Kas↔Piutang.
 */
class InstallmentService
{
    public function __construct(private JournalService $journal) {}

    /**
     * Buat rencana cicilan (status draft/pending_approval) dari tagihan tunggakan.
     *
     * @param  array<int>  $billIds
     */
    public function createPlan(Customer $customer, array $billIds, int $tenorMonths, ?int $createdBy = null): InstallmentPlan
    {
        if ($tenorMonths < 1) {
            throw new RuntimeException('Tenor cicilan minimal 1 bulan.');
        }

        /** @var Collection<int, Bill> $bills */
        $bills = Bill::whereIn('id', $billIds)
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['unpaid', 'overdue'])
            ->get();

        if ($bills->isEmpty()) {
            throw new RuntimeException('Tidak ada tagihan tunggakan yang valid untuk dicicil.');
        }

        $total = (float) $bills->sum('amount_due');
        $monthly = round($total / $tenorMonths, 2);

        return DB::transaction(function () use ($customer, $bills, $tenorMonths, $total, $monthly, $createdBy) {
            $plan = InstallmentPlan::create([
                'customer_id' => $customer->id,
                'plan_number' => $this->generatePlanNumber(),
                'total_amount' => $total,
                'tenor_months' => $tenorMonths,
                'monthly_amount' => $monthly,
                'status' => 'pending_approval',
                'created_by' => $createdBy,
            ]);

            foreach ($bills as $bill) {
                $plan->planBills()->create([
                    'pdam_org_id' => $plan->pdam_org_id,
                    'bill_id' => $bill->id,
                    'amount' => $bill->amount_due,
                ]);
            }

            return $plan->load('planBills');
        });
    }

    /** Approval final (direktur) → aktifkan cicilan, buat jadwal termin. */
    public function approve(InstallmentPlan $plan, int $directorId): InstallmentPlan
    {
        if ($plan->status !== 'pending_approval') {
            throw new RuntimeException('Rencana cicilan tidak dalam status menunggu persetujuan.');
        }

        return DB::transaction(function () use ($plan, $directorId) {
            $plan->update([
                'status' => 'active',
                'director_approved_by' => $directorId,
                'director_approved_at' => now(),
            ]);

            // Buat jadwal termin. Termin terakhir menyerap selisih pembulatan.
            $accumulated = 0.0;
            for ($i = 1; $i <= $plan->tenor_months; $i++) {
                $amount = $i === $plan->tenor_months
                    ? round($plan->total_amount - $accumulated, 2)
                    : (float) $plan->monthly_amount;
                $accumulated += $amount;

                $plan->schedules()->create([
                    'pdam_org_id' => $plan->pdam_org_id,
                    'installment_no' => $i,
                    'amount' => $amount,
                    'due_date' => Carbon::now()->addMonthsNoOverflow($i)->startOfMonth()->addDays(19),
                    'status' => 'unpaid',
                ]);
            }

            // Tagihan asli dipindah ke skema cicilan (status waived agar tak dobel tagih)
            $billIds = $plan->planBills()->pluck('bill_id');
            Bill::whereIn('id', $billIds)->update(['status' => 'waived']);

            return $plan->load('schedules');
        });
    }

    public function reject(InstallmentPlan $plan, string $reason, int $by): InstallmentPlan
    {
        if ($plan->status !== 'pending_approval') {
            throw new RuntimeException('Hanya rencana menunggu persetujuan yang bisa ditolak.');
        }

        $plan->update(['status' => 'rejected', 'rejection_reason' => $reason, 'approved_by' => $by]);

        return $plan;
    }

    /** Bayar satu termin cicilan + auto-jurnal Kas↔Piutang. */
    public function payInstallment(InstallmentSchedule $schedule, ?int $paymentId = null): InstallmentSchedule
    {
        if ($schedule->status === 'paid') {
            return $schedule; // idempotent
        }

        return DB::transaction(function () use ($schedule, $paymentId) {
            $schedule->update(['status' => 'paid', 'paid_at' => now(), 'payment_id' => $paymentId]);

            $this->journal->record(
                "Pembayaran cicilan termin #{$schedule->installment_no}",
                [
                    ['account_code' => '1-001', 'type' => 'DEBIT', 'amount' => $schedule->amount, 'memo' => 'Kas/Bank'],
                    ['account_code' => '1-002', 'type' => 'KREDIT', 'amount' => $schedule->amount, 'memo' => 'Pelunasan piutang cicilan'],
                ],
                'INSTALLMENT',
                $schedule->id,
            );

            // Jika seluruh termin lunas → tandai plan completed
            $plan = InstallmentPlan::find($schedule->plan_id);
            if ($plan && ! $plan->schedules()->where('status', '!=', 'paid')->exists()) {
                $plan->update(['status' => 'completed']);
            }

            return $schedule->fresh();
        });
    }

    protected function generatePlanNumber(): string
    {
        $org = TenantContext::id();

        return sprintf('CIC-%s-%s', $org, strtoupper(uniqid()));
    }
}
