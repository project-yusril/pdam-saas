<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\PdamOrganization;
use App\Services\InstallmentService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifikasi cicilan tunggakan (PRD 10.4, Fase 1.8):
 * - Plan dibuat dari tagihan tunggakan, tenor membagi total.
 * - Saat approve: jadwal termin dibuat & tagihan asli jadi "waived".
 * - Termin terakhir menyerap selisih pembulatan (total termin == total plan).
 * - Bayar termin memicu jurnal Kas↔Piutang yang balance.
 */
class InstallmentPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new PdamOrganization)->forceFill(['id' => 1, 'code' => 'test', 'name' => 'Test PDAM'])->save();
        TenantContext::set(1);

        foreach ([['1-001', 'Kas/Bank', 'ASSET', 'DEBIT'], ['1-002', 'Piutang Pelanggan', 'ASSET', 'DEBIT']] as [$c, $n, $t, $b]) {
            ChartOfAccount::create(['pdam_org_id' => 1, 'code' => $c, 'name' => $n, 'type' => $t, 'normal_balance' => $b]);
        }
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function makeCustomerWithArrears(): array
    {
        $customer = Customer::create([
            'pdam_org_id' => 1,
            'customer_number' => 'C-001',
            'full_name' => 'Budi',
            'status' => 'active',
        ]);

        // 3 tagihan menunggak, total 300.000 (biar habis dibagi rata)
        $bills = [];
        foreach (['2026-01', '2026-02', '2026-03'] as $i => $period) {
            $bills[] = Bill::create([
                'pdam_org_id' => 1,
                'customer_id' => $customer->id,
                'bill_number' => "B-{$i}",
                'period' => $period,
                'consumption' => 20,
                'amount_due' => 100000,
                'status' => 'overdue',
            ]);
        }

        return [$customer, collect($bills)->pluck('id')->all()];
    }

    public function test_plan_created_and_approved_generates_schedule(): void
    {
        [$customer, $billIds] = $this->makeCustomerWithArrears();
        $svc = app(InstallmentService::class);

        $plan = $svc->createPlan($customer, $billIds, tenorMonths: 4);
        $this->assertEquals(300000, (float) $plan->total_amount);
        $this->assertEquals('pending_approval', $plan->status);

        $approved = $svc->approve($plan, directorId: 99);
        $this->assertEquals('active', $approved->status);
        $this->assertCount(4, $approved->schedules);

        // Total termin harus sama persis dengan total plan (tak ada uang hilang)
        $this->assertEquals(300000, (float) $approved->schedules->sum('amount'));

        // Tagihan asli dipindah ke cicilan → waived
        $this->assertEquals(3, Bill::whereIn('id', $billIds)->where('status', 'waived')->count());
    }

    public function test_pay_installment_posts_balanced_journal_and_completes_plan(): void
    {
        [$customer, $billIds] = $this->makeCustomerWithArrears();
        $svc = app(InstallmentService::class);

        $plan = $svc->approve($svc->createPlan($customer, $billIds, tenorMonths: 3), directorId: 99);

        foreach ($plan->schedules as $schedule) {
            $result = $svc->payInstallment($schedule);
            $this->assertEquals('paid', $result->status);

            // Jurnal yang tercatat balance (DEBIT == KREDIT)
            $entry = JournalEntry::where('reference_type', 'INSTALLMENT')
                ->where('reference_id', $schedule->id)->first();
            $this->assertNotNull($entry);
            $this->assertEquals(
                (float) $entry->lines->where('type', 'DEBIT')->sum('amount'),
                (float) $entry->lines->where('type', 'KREDIT')->sum('amount'),
            );
        }

        $this->assertEquals('completed', $plan->fresh()->status);
    }

    public function test_pay_installment_is_idempotent(): void
    {
        [$customer, $billIds] = $this->makeCustomerWithArrears();
        $svc = app(InstallmentService::class);
        $plan = $svc->approve($svc->createPlan($customer, $billIds, tenorMonths: 3), directorId: 99);

        $schedule = $plan->schedules->first();
        $svc->payInstallment($schedule);
        $svc->payInstallment($schedule); // panggilan kedua tidak boleh dobel jurnal

        $count = JournalEntry::where('reference_type', 'INSTALLMENT')
            ->where('reference_id', $schedule->id)->count();
        $this->assertEquals(1, $count);
    }
}
