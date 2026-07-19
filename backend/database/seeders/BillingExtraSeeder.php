<?php

namespace Database\Seeders;

use App\Models\PdamOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * BillingExtraSeeder — modul BILL+ (angsuran & penyesuaian tagihan).
 *
 * Mengisi:
 *  - installment_plans / installment_plan_bills / installment_schedules
 *  - bill_adjustments
 *
 * Catatan: transaksi dibuat dalam status non-posting (draft/pending/approved tanpa
 * posting GL) sehingga TIDAK memengaruhi keseimbangan neraca hasil seeder inti.
 * Idempotent: dilewati bila installment_plans sudah ada.
 */
class BillingExtraSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('installment_plans')->exists()) {
            return;
        }

        foreach (['pdam-canada', 'pdam-brazil'] as $code) {
            $org = PdamOrganization::where('code', $code)->first();
            if (! $org) {
                continue;
            }
            $this->seedForTenant($org->id);
        }
    }

    private function seedForTenant(int $orgId): void
    {
        // Ambil pelanggan yang punya tagihan menunggak untuk dibuatkan cicilan
        $unpaidBills = DB::table('bills')
            ->where('pdam_org_id', $orgId)
            ->whereIn('status', ['unpaid', 'overdue', 'partially_paid'])
            ->orderBy('customer_id')
            ->get();

        if ($unpaidBills->isEmpty()) {
            // fallback: pakai tagihan mana pun
            $unpaidBills = DB::table('bills')->where('pdam_org_id', $orgId)->limit(2)->get();
        }

        $byCustomer = $unpaidBills->groupBy('customer_id');
        $planSeq = 1;

        foreach ($byCustomer->take(2) as $customerId => $bills) {
            $totalAmount = (float) $bills->sum('total_amount');
            if ($totalAmount <= 0) {
                $totalAmount = 300_000; // jaga-jaga bila kolom berbeda
            }
            $tenor = 3;
            $monthly = round($totalAmount / $tenor, 2);

            $planId = DB::table('installment_plans')->insertGetId([
                'pdam_org_id' => $orgId,
                'customer_id' => $customerId,
                'plan_number' => sprintf('INST-%d-%04d', $orgId, $planSeq++),
                'total_amount' => $totalAmount,
                'tenor_months' => $tenor,
                'monthly_amount' => $monthly,
                'status' => 'active',
                'approved_at' => now()->subDays(15),
                'created_at' => now()->subDays(16),
                'updated_at' => now()->subDays(15),
            ]);

            // Tagihan yang dicicil
            foreach ($bills as $bill) {
                DB::table('installment_plan_bills')->insert([
                    'pdam_org_id' => $orgId,
                    'plan_id' => $planId,
                    'bill_id' => $bill->id,
                    'amount' => $bill->total_amount ?? $monthly,
                    'created_at' => now()->subDays(15),
                    'updated_at' => now()->subDays(15),
                ]);
            }

            // Jadwal termin
            for ($n = 1; $n <= $tenor; $n++) {
                DB::table('installment_schedules')->insert([
                    'pdam_org_id' => $orgId,
                    'plan_id' => $planId,
                    'installment_no' => $n,
                    'amount' => $monthly,
                    'due_date' => now()->addMonths($n - 1)->toDateString(),
                    'status' => $n === 1 ? 'paid' : 'unpaid',
                    'paid_at' => $n === 1 ? now()->subDays(10) : null,
                    'created_at' => now()->subDays(15),
                    'updated_at' => now()->subDays(15),
                ]);
            }
        }

        // Penyesuaian tagihan (koreksi turun) — status approved tanpa posting GL
        $sampleBill = DB::table('bills')->where('pdam_org_id', $orgId)->first();
        $requester = DB::table('users')->where('pdam_org_id', $orgId)->first();
        if ($sampleBill && $requester) {
            $old = (float) ($sampleBill->total_amount ?? 200_000);
            $new = round($old * 0.9, 2);
            DB::table('bill_adjustments')->insert([
                'pdam_org_id' => $orgId,
                'bill_id' => $sampleBill->id,
                'type' => 'correction',
                'reason' => 'Koreksi kesalahan baca meter',
                'old_amount' => $old,
                'new_amount' => $new,
                'adjustment_amount' => $new - $old,
                'requested_by' => $requester->id,
                'approved_by' => $requester->id,
                'status' => 'approved',
                'notes' => 'Disetujui setelah verifikasi ulang angka meter.',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ]);
        }
    }
}
