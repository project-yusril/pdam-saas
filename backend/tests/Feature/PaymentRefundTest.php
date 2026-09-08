<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Module;
use App\Models\Payment;
use App\Models\PdamOrganization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\RefundException;
use App\Services\RefundService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pembayaran refund (fitur PRD §23) — di-gate; service menolak bila
 * `business.refund.enabled=false`. Full/partial: jurnal pembalikan tetap
 * balance (DEBIT=KREDIT) & status Payment beralih `refunded` hanya saat penuh.
 */
class PaymentRefundTest extends TestCase
{
    use RefreshDatabase;

    private int $orgId;

    private Customer $customer;

    private Payment $payment;

    private Bill $bill;

    protected function setUp(): void
    {
        parent::setUp();

        $org = PdamOrganization::create([
            'code' => 'rf'.uniqid(),
            'name' => 'PDAM Refund',
            'subscription_status' => 'active',
        ]);
        $this->orgId = $org->id;
        TenantContext::set($org->id);

        ChartOfAccount::insert([
            ['pdam_org_id' => $org->id, 'code' => '1-001', 'name' => 'Kas', 'type' => 'ASSET', 'normal_balance' => 'DEBIT', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['pdam_org_id' => $org->id, 'code' => '1-002', 'name' => 'Piutang', 'type' => 'ASSET', 'normal_balance' => 'DEBIT', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->customer = Customer::create([
            'pdam_org_id' => $org->id,
            'customer_number' => 'C-RF-1',
            'full_name' => 'Uji Refund',
            'status' => 'active',
        ]);

        $this->bill = Bill::create([
            'pdam_org_id' => $org->id,
            'customer_id' => $this->customer->id,
            'bill_number' => 'BL-RF-1',
            'period' => now()->format('Y-m'),
            'amount_due' => 100000,
            'status' => 'unpaid',
            'due_date' => now()->addDays(15)->toDateString(),
        ]);

        $payments = app(PaymentService::class);
        $payment = $payments->createForBill($this->bill, 'counter');
        $this->payment = $payments->markPaid($payment, null, 'cash');
    }

    public function test_refund_disabled_by_default(): void
    {
        config(['business.refund.enabled' => false]);
        $this->expectException(RefundException::class);
        app(RefundService::class)->refund($this->payment, 1, null, 'test');
    }

    public function test_refund_disabled_at_endpoint(): void
    {
        [$u, $r] = $this->financeUser();
        config(['business.refund.enabled' => false]);

        $this->actingAs($u, 'sanctum')->postJson('/api/v1/payments/'.$this->payment->id.'/refund', ['reason' => 'x'])
            ->assertStatus(422);
    }

    public function test_full_refund_creates_reversed_balanced_journal_and_marks_paid_and_status(): void
    {
        config(['business.refund.enabled' => true]);
        [$u] = $this->financeUser();

        $refunds = $this->payment->refunds()->count();
        $this->assertSame(0, $refunds);

        $this->actingAs($u, 'sanctum')
            ->postJson('/api/v1/payments/'.$this->payment->id.'/refund', ['reason' => 'kelebihan bayar'])
            ->assertCreated();

        $r = $this->payment->refunds()->sole();
        $this->assertEquals(100000.0, (float) $r->amount);
        $this->payment->refresh();
        $this->assertSame('refunded', $this->payment->status);

        $j = JournalEntry::with('lines.account')->find($r->journal_entry_id);
        $this->assertNotNull($j);
        // pembalikan: DEBIT piutang / KREDIT kas (asalnya DEBIT kas / KREDIT piutang)
        $origLines = JournalEntry::find($this->payment->journal_entry_id)->lines()->with('account')->get();
        // pembalikan: asal DEBIT kas/KREDIT piutang -> jadi DEBIT piutang/KREDIT kas
        $this->assertSame(
            ['1-001', '1-002'],
            $j->lines->sortBy('account.code')->pluck('account.code')->all(),
        );
        $this->assertSame(
            ['KREDIT', 'DEBIT'],
            $j->lines->sortBy('account.code')->pluck('type')->all(),
        );
        $this->assertEquals(0, (float) $j->lines->where('type', 'DEBIT')->sum('amount') - (float) $j->lines->where('type', 'KREDIT')->sum('amount'));

        // piutang kas tidak berubah double?
        $this->assertNotSame(0, (int) $origLines->count());
    }

    public function test_partial_and_multi_refund_limited_to_remaining(): void
    {
        config(['business.refund.enabled' => true]);
        [$u] = $this->financeUser();

        $this->actingAs($u, 'sanctum')
            ->postJson('/api/v1/payments/'.$this->payment->id.'/refund', ['amount' => 40000, 'reason' => 'partial'])
            ->assertCreated();
        $this->assertFalse($this->payment->fresh()->isRefundable() ? $this->payment->fresh()->status === 'refunded' : true);
        $this->assertSame('success', $this->payment->fresh()->status);

        $this->actingAs($u, 'sanctum')
            ->postJson('/api/v1/payments/'.$this->payment->id.'/refund', ['amount' => 70000])
            ->assertStatus(422);

        $this->actingAs($u, 'sanctum')
            ->postJson('/api/v1/payments/'.$this->payment->id.'/refund', ['amount' => 60000])
            ->assertCreated();
        $this->assertSame('refunded', $this->payment->fresh()->status);

        $this->assertSame(2, $this->payment->refunds()->count());
        $this->assertEquals(100000, (float) $this->payment->refunds()->sum('amount'));
    }

    public function test_permission_required_to_refund(): void
    {
        config(['business.refund.enabled' => true]);
        [$u] = $this->viewerUser();
        $this->actingAs($u, 'sanctum')->postJson('/api/v1/payments/'.$this->payment->id.'/refund')->assertForbidden();
    }

    public function test_cross_tenant_refund_blocked(): void
    {
        config(['business.refund.enabled' => true]);

        // user+permission di tenant LAIN mencoba me-refund payment tenant ini
        $other = PdamOrganization::create(['code' => 'oth'.uniqid(), 'name' => 'Other PDAM', 'subscription_status' => 'active']);
        $perm = Permission::firstOrCreate(['code' => 'core.payment.refund'], ['module_code' => 'CORE', 'resource' => 'payment', 'action' => 'refund']);
        $role = Role::firstOrCreate(['pdam_org_id' => $other->id, 'code' => 'keu-cross'], ['name' => 'Keuangan Cross']);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $u = User::create([
            'pdam_org_id' => $other->id,
            'name' => 'cross'.uniqid(),
            'email' => uniqid().'@cross.com',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
        $u->roles()->attach($role);

        $this->actingAs($u, 'sanctum')
            ->postJson('/api/v1/payments/'.$this->payment->id.'/refund', ['method' => 'transfer'])
            ->assertNotFound();
    }

    private function financeUser(): array
    {
        $module = Module::firstOrCreate(['code' => 'CORE'], ['name' => 'Core', 'is_active' => true]);
        $role = Role::firstOrCreate(['code' => 'staff-keuangan-rf', 'pdam_org_id' => $this->orgId], ['name' => 'Keuangan RF']);
        foreach (['core.payment.refund', 'core.payment.view'] as $code) {
            $perm = Permission::firstOrCreate(['code' => $code], [
                'module_code' => 'CORE', 'resource' => 'payment', 'action' => str_contains($code, 'refund') ? 'refund' : 'view',
            ]);
            $role->permissions()->syncWithoutDetaching([$perm->id]);
        }

        $u = User::create([
            'pdam_org_id' => $this->orgId,
            'name' => 'keu'.uniqid(),
            'email' => 'k'.uniqid().'@rf.com',
            'password' => bcrypt('P@ssw0rd!'),
            'is_active' => true,
        ]);
        $u->roles()->attach($role);

        return [$u, $role];
    }

    private function viewerUser(): array
    {
        $role = Role::firstOrCreate(['code' => 'staff-viewer-rf', 'pdam_org_id' => $this->orgId], ['name' => 'Viewer RF']);
        $perm = Permission::firstOrCreate(['code' => 'core.payment.view'], ['module_code' => 'CORE', 'resource' => 'payment', 'action' => 'view']);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $u = User::create([
            'pdam_org_id' => $this->orgId,
            'name' => 'vi'.uniqid(),
            'email' => 'v'.uniqid().'@rf.com',
            'password' => bcrypt('P@ssw0rd!'),
            'is_active' => true,
        ]);
        $u->roles()->attach($role);

        return [$u, $role];
    }
}
