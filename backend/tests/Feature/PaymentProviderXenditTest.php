<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\PdamOrganization;
use App\Services\Gateways\MidtransSnap;
use App\Services\Gateways\PaymentGatewayManager;
use App\Services\Gateways\XenditCharge;
use App\Services\PaymentService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Provider pembayaran KEDUA (PRD §23). Menutup klaim "provider selain Midtrans = roadmap":
 * kini tersedia XenditCharge + PaymentGatewayManager + webhook publik terverifikasi.
 */
class PaymentProviderXenditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $org = PdamOrganization::create([
            'code' => 'xnd'.uniqid(),
            'name' => 'PDAM X',
            'subscription_status' => 'active',
        ]);
        $this->orgId = $org->id;
        TenantContext::set($this->orgId);

        // Jurnal markPaid butuh COA kas/piutang
        ChartOfAccount::insert([
            ['pdam_org_id' => $this->orgId, 'code' => '1-001', 'name' => 'Kas', 'type' => 'ASSET', 'normal_balance' => 'DEBIT', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['pdam_org_id' => $this->orgId, 'code' => '1-002', 'name' => 'Piutang pelanggan', 'type' => 'ASSET', 'normal_balance' => 'DEBIT', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $customer = Customer::create(['pdam_org_id' => $this->orgId, 'customer_number' => 'CXND-1', 'full_name' => 'Test', 'status' => 'active']);
        $this->bill = Bill::create([
            'pdam_org_id' => $this->orgId,
            'customer_id' => $customer->id,
            'bill_number' => 'BXND-'.now()->format('my').uniqid(),
            'period' => now()->format('Y-m'),
            'amount_due' => 250000,
            'status' => 'unpaid',
            'due_date' => now()->addDays(15)->toDateString(),
        ]);

        // token callback test + secret key dummy utk test HTTP
        config(['services.xendit.secret_key' => 'xnd_secret', 'services.xendit.webhook_token' => 'testtoken123',
            'business.payment.providers' => ['midtrans' => MidtransSnap::class, 'xendit' => XenditCharge::class],
            'business.payment.default_provider' => 'midtrans',
        ]);
    }

    private int $orgId;

    private Bill $bill;

    public function test_manager_default_midtrans_and_resolves_xendit_when_requested(): void
    {
        $manager = app(PaymentGatewayManager::class);

        $this->assertInstanceOf(MidtransSnap::class, $manager->make());
        $this->assertInstanceOf(XenditCharge::class, $manager->make('xendit'));
        $this->assertEquals(['midtrans', 'xendit'], $manager->allowed());
        $this->expectException(\RuntimeException::class);
        $manager->make('stripe');
    }

    public function test_xendit_charge_adapter_flow(): void
    {
        Http::fake([
            'https://api.xendit.co/payment_methods' => Http::response(['id' => 'pm_123']),
            'https://api.xendit.co/payment_intents' => Http::response([
                'id' => 'pi_1',
                'actions' => [['url' => 'https://pay.xendit.test/pi_1']],
            ]),
        ]);

        $gateway = new XenditCharge;
        $payment = app(PaymentService::class)->createForBill($this->bill, channel: 'gateway', method: null);

        $resp = $gateway->createTransaction($payment, ['name' => 'Xendi T']);
        $this->assertSame('https://pay.xendit.test/pi_1', $resp['redirect_url']);
        $this->assertSame('pi_1', $resp['token']);

        Http::fake(['*/payment_intents/pi_1' => Http::response(['id' => 'pi_1', 'status' => 'SUCCEEDED'])]);
        $status = $gateway->getStatus('pi_1');
        $this->assertSame('SUCCEEDED', $status['transaction_status']);

        // checkSignature: benar & salah
        $this->assertTrue($gateway->checkSignature(['callback_token' => 'testtoken123']));
        $this->assertFalse($gateway->checkSignature(['callback_token' => 'wrong']));
    }

    public function test_xendit_webhook_marks_payment_paid(): void
    {
        $payment = app(PaymentService::class)->createForBill($this->bill, 'gateway');
        $payment->update(['midtrans_transaction_id' => 'pi-webhook-1']);

        $resp = $this->postJson('/api/v1/webhooks/xendit', [
            'id' => 'pi-webhook-1',
            'status' => 'SUCCEEDED',
            'event' => 'payment_intent.succeeded',
        ], ['x-callback-token' => 'testtoken123'])->assertOk();

        $this->assertSame('success', $payment->fresh()->status);
        $resp->assertJsonPath('message', 'Xendit webhook diproses.');
    }

    public function test_webhook_rejects_bad_callback_token(): void
    {
        $payment = app(PaymentService::class)->createForBill($this->bill, 'gateway');
        $payment->update(['midtrans_transaction_id' => 'pi-bad-1']);

        // no header -> checkSignature false
        $this->postJson('/api/v1/webhooks/xendit', ['id' => 'pi-bad-1', 'status' => 'SUCCEEDED', 'event' => 'x'])
            ->assertStatus(403);
    }

    public function test_interface_contract_is_satisfied_by_xendit(): void
    {
        $this->assertInstanceOf(PaymentGatewayInterface::class, new XenditCharge);
    }
}
