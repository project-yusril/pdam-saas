<?php

namespace Tests\Feature;

use App\Exceptions\MarketplaceException;
use App\Models\Module;
use App\Models\PdamOrganization;
use App\Models\User;
use App\Services\SaasPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TenantMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            '*/snap/v1/transactions' => Http::response([
                'token' => 'snap-token',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token',
            ]),
        ]);
    }

    public function test_catalog_uses_authenticated_tenant_pricing_and_purchase_stays_pending(): void
    {
        [$org, $user] = $this->tenant('one');
        [$other, $otherUser] = $this->tenant('two');
        Module::create(['code' => 'CRM', 'name' => 'CRM', 'base_price_year' => 100000, 'is_active' => true]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/marketplace/catalog?organization_id='.$other->id)
            ->assertOk()->assertJsonPath('data.modules.0.price_year', 100000)->assertJsonPath('data.modules.0.entitled', false);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/marketplace/purchases', [
            'organization_id' => $other->id,
            'items' => [['type' => 'module', 'code' => 'CRM']],
        ], ['Idempotency-Key' => 'purchase-1'])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_url', 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token');

        $this->assertDatabaseHas('saas_purchase_orders', ['order_number' => $response->json('data.order_number'), 'pdam_org_id' => $org->id, 'status' => 'pending']);
        $this->assertDatabaseMissing('subscription_modules', ['pdam_org_id' => $org->id, 'module_code' => 'CRM', 'status' => 'active']);
        $this->assertDatabaseMissing('saas_purchase_orders', ['pdam_org_id' => $other->id]);
        Http::assertSent(fn ($request) => $request['transaction_details']['order_id'] === $response->json('data.order_number')
            && $request['transaction_details']['gross_amount'] === 100000
            && $request['item_details'][0]['id'] === 'CRM');
    }

    public function test_purchase_dependency_enforcement_and_request_idempotency(): void
    {
        [, $user] = $this->tenant('deps');
        Module::create(['code' => 'CRM', 'name' => 'CRM', 'base_price_year' => 100, 'dependencies' => ['CORE'], 'is_active' => true]);

        $payload = ['items' => [['type' => 'module', 'code' => 'CRM']]];
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/marketplace/purchases', $payload, ['Idempotency-Key' => 'deps-1'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'DEPENDENCY_MISSING');

        Module::create(['code' => 'CORE', 'name' => 'Core', 'base_price_year' => 0, 'is_active' => true]);
        $first = $this->actingAs($user, 'sanctum')->postJson('/api/v1/marketplace/purchases', [
            'items' => [['type' => 'module', 'code' => 'CORE'], ['type' => 'module', 'code' => 'CRM']],
        ], ['Idempotency-Key' => 'deps-2'])->assertCreated();
        $second = $this->actingAs($user, 'sanctum')->postJson('/api/v1/marketplace/purchases', $payload, ['Idempotency-Key' => 'deps-2'])->assertOk();
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('saas_purchase_orders', 1);
        $this->assertDatabaseCount('saas_purchase_order_lines', 2);
    }

    public function test_verified_settlement_is_idempotent_and_activates_all_lines_atomically(): void
    {
        [$org, $user] = $this->tenant('settle');
        Module::create(['code' => 'CORE', 'name' => 'Core', 'base_price_year' => 0, 'is_active' => true]);
        Module::create(['code' => 'CRM', 'name' => 'CRM', 'base_price_year' => 100, 'dependencies' => ['CORE'], 'is_active' => true]);
        $order = app(SaasPurchaseService::class)->create($user, [['type' => 'module', 'code' => 'CORE'], ['type' => 'module', 'code' => 'CRM']], 'settle-1');
        config(['services.midtrans.server_key' => 'secret']);
        $payload = ['order_id' => $order->order_number, 'status_code' => '200', 'gross_amount' => '100.00', 'transaction_status' => 'settlement', 'transaction_id' => 'gateway-1'];

        $this->postJson('/api/v1/webhooks/midtrans', [...$payload, 'signature_key' => 'invalid'])->assertForbidden();
        $this->assertDatabaseMissing('subscription_modules', ['pdam_org_id' => $org->id, 'status' => 'active']);

        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'secret');
        $this->postJson('/api/v1/webhooks/midtrans', $payload)->assertOk();
        $this->postJson('/api/v1/webhooks/midtrans', $payload)->assertOk();
        $this->assertDatabaseCount('subscription_modules', 2);
        $this->assertDatabaseCount('saas_invoices', 1);
        $this->assertDatabaseHas('saas_purchase_orders', ['id' => $order->id, 'status' => 'settled', 'gateway_transaction_id' => 'gateway-1']);
    }

    public function test_settlement_dependency_failure_rolls_back_every_activation(): void
    {
        [$org, $user] = $this->tenant('atomic');
        Module::create(['code' => 'CORE', 'name' => 'Core', 'base_price_year' => 0, 'is_active' => true]);
        $crm = Module::create(['code' => 'CRM', 'name' => 'CRM', 'base_price_year' => 100, 'dependencies' => ['CORE'], 'is_active' => true]);
        $order = app(SaasPurchaseService::class)->create($user, [['type' => 'module', 'code' => 'CORE'], ['type' => 'module', 'code' => 'CRM']], 'atomic-1');
        $crm->update(['dependencies' => ['CORE', 'MISSING']]);

        try {
            app(SaasPurchaseService::class)->settle($order->order_number, 'failed');
            $this->fail('Settlement should fail.');
        } catch (MarketplaceException $exception) {
            $this->assertSame('DEPENDENCY_MISSING', $exception->errorCode);
        }
        $this->assertDatabaseMissing('subscription_modules', ['pdam_org_id' => $org->id, 'status' => 'active']);
        $this->assertDatabaseHas('saas_purchase_orders', ['id' => $order->id, 'status' => 'pending']);
    }

    private function tenant(string $code): array
    {
        $org = PdamOrganization::create(['code' => $code, 'name' => $code, 'subscription_status' => 'active']);
        $user = User::create(['pdam_org_id' => $org->id, 'name' => $code, 'email' => $code.'@test.local', 'password' => 'password', 'is_tenant_admin' => true, 'is_active' => true]);

        return [$org, $user];
    }
}
