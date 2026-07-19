<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\ModuleBundle;
use App\Models\ModuleBundleItem;
use App\Models\ModulePriceTier;
use App\Models\PdamOrganization;
use App\Models\PlatformAdmin;
use App\Models\Subscription;
use App\Models\SubscriptionModule;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CriticalCommerceSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_cannot_bypass_locked_paid_module(): void
    {
        [$org, $admin] = $this->tenantAdmin();
        $this->module('CRM', ['CORE']);
        SubscriptionModule::create([
            'pdam_org_id' => $org->id,
            'module_code' => 'CRM',
            'status' => 'locked',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/complaints')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'MODULE_LOCKED');

        SubscriptionModule::where('pdam_org_id', $org->id)
            ->where('module_code', 'CRM')
            ->update(['status' => 'active', 'activated_at' => now()]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/complaints')
            ->assertOk();
    }

    public function test_fin_permission_maps_to_fin_plus_entitlement(): void
    {
        [$org, $admin] = $this->tenantAdmin();
        $this->module('FIN+', ['CORE']);
        SubscriptionModule::create([
            'pdam_org_id' => $org->id,
            'module_code' => 'FIN+',
            'status' => 'locked',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/fin/budgets')
            ->assertForbidden()
            ->assertJsonPath('module', 'FIN+');
    }

    public function test_all_paid_laravel_modules_reject_locked_tenant_admin(): void
    {
        [$org, $admin] = $this->tenantAdmin();
        $endpoints = [
            'ZONE' => '/api/v1/zones',
            'SRV' => '/api/v1/prospects',
            'MTR' => '/api/v1/meter-routes',
            'WH' => '/api/v1/purchase-orders',
            'METX' => '/api/v1/meters',
            'AST' => '/api/v1/assets',
            'FIN+' => '/api/v1/fin/budgets',
            'CRM' => '/api/v1/complaints',
            'C360' => '/api/v1/customer-view/quick-search?q=AA',
            'BILL+' => '/api/v1/bill-adjustments',
            'APP' => '/api/v1/portal/dashboard',
            'CHEM' => '/api/v1/chem/chemicals',
            'PROC' => '/api/v1/vendors',
            'FSM' => '/api/v1/work-orders',
            'MNT' => '/api/v1/maintenance/schedules',
            'HR' => '/api/v1/employees',
            'DMS' => '/api/v1/documents',
            'GIS' => '/api/v1/gis/features',
            'CC' => '/api/v1/call-logs',
            'BI' => '/api/v1/bi/kpi-eksekutif',
            'INT' => '/api/v1/integrations',
            'IOT' => '/api/v1/iot/dashboard',
            'PROD' => '/api/v1/production/dashboard',
            'DIST' => '/api/v1/distribution/dma-dashboard',
            'NRW' => '/api/v1/nrw/dashboard',
        ];

        foreach ($endpoints as $moduleCode => $endpoint) {
            $this->module($moduleCode);
            SubscriptionModule::create([
                'pdam_org_id' => $org->id,
                'module_code' => $moduleCode,
                'status' => 'locked',
            ]);

            $this->actingAs($admin, 'sanctum')
                ->getJson($endpoint)
                ->assertForbidden()
                ->assertJsonPath('module', $moduleCode)
                ->assertJsonPath('error_code', 'MODULE_LOCKED');
        }
    }

    public function test_marketplace_purchase_activates_subscription_module_atomically(): void
    {
        [$org] = $this->tenantAdmin();
        $this->module('CORE', [], true);
        $mtr = $this->module('MTR', ['CORE']);
        ModulePriceTier::create([
            'module_code' => 'MTR',
            'min_customers' => 0,
            'max_customers' => null,
            'price_year' => 15000000,
        ]);
        SubscriptionModule::create([
            'pdam_org_id' => $org->id,
            'module_code' => 'CORE',
            'status' => 'active',
            'activation_method' => 'free_default',
        ]);
        SubscriptionModule::create([
            'pdam_org_id' => $org->id,
            'module_code' => $mtr->code,
            'status' => 'locked',
        ]);

        Sanctum::actingAs($this->platformAdmin(), ['platform']);

        $this->postJson('/api/v1/platform/marketplace/purchase', [
            'organization_id' => $org->id,
            'items' => [['type' => 'module', 'code' => 'MTR']],
        ])->assertCreated()
            ->assertJsonPath('data.modules.0', 'MTR')
            ->assertJsonPath('data.final_price', 15000000);

        $this->assertDatabaseHas('subscription_modules', [
            'pdam_org_id' => $org->id,
            'module_code' => 'MTR',
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('tenant_module_overrides', 0);
        $this->assertDatabaseHas('subscriptions', [
            'pdam_org_id' => $org->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('saas_invoices', [
            'pdam_org_id' => $org->id,
            'amount' => 15000000,
            'status' => 'paid',
        ]);
    }

    public function test_marketplace_rejects_missing_dependency_without_partial_activation(): void
    {
        [$org] = $this->tenantAdmin();
        $this->module('MTR', ['CORE']);
        $this->module('METX', ['MTR']);
        SubscriptionModule::create([
            'pdam_org_id' => $org->id,
            'module_code' => 'METX',
            'status' => 'locked',
        ]);
        Sanctum::actingAs($this->platformAdmin(), ['platform']);

        $this->postJson('/api/v1/platform/marketplace/purchase', [
            'organization_id' => $org->id,
            'items' => [['type' => 'module', 'code' => 'METX']],
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'DEPENDENCY_MISSING');

        $this->assertDatabaseHas('subscription_modules', [
            'pdam_org_id' => $org->id,
            'module_code' => 'METX',
            'status' => 'locked',
        ]);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('saas_invoices', 0);
    }

    public function test_marketplace_uses_bundle_price_instead_of_unit_price_sum(): void
    {
        [$org] = $this->tenantAdmin();
        $core = $this->module('CORE', [], true);
        $mtr = $this->module('MTR', ['CORE']);
        $crm = $this->module('CRM', ['CORE']);
        $bundle = ModuleBundle::create([
            'code' => 'FIELD',
            'name' => 'Field Bundle',
            'price_year' => 1200000,
        ]);
        ModuleBundleItem::create(['module_bundle_id' => $bundle->id, 'module_id' => $mtr->id]);
        ModuleBundleItem::create(['module_bundle_id' => $bundle->id, 'module_id' => $crm->id]);
        SubscriptionModule::create([
            'pdam_org_id' => $org->id,
            'module_code' => $core->code,
            'status' => 'active',
        ]);
        foreach ([$mtr, $crm] as $module) {
            SubscriptionModule::create([
                'pdam_org_id' => $org->id,
                'module_code' => $module->code,
                'status' => 'locked',
            ]);
        }
        Sanctum::actingAs($this->platformAdmin(), ['platform']);

        $this->postJson('/api/v1/platform/marketplace/purchase', [
            'organization_id' => $org->id,
            'items' => [['type' => 'bundle', 'code' => 'FIELD']],
        ])->assertCreated()
            ->assertJsonPath('data.final_price', 1200000);

        $this->assertDatabaseHas('saas_invoices', [
            'pdam_org_id' => $org->id,
            'amount' => 1200000,
        ]);
    }

    public function test_bundle_items_are_unique_and_deleted_with_bundle(): void
    {
        $module = $this->module('CRM', ['CORE']);
        $bundle = ModuleBundle::create([
            'code' => 'UNIQUE',
            'name' => 'Unique Bundle',
            'price_year' => 1000000,
        ]);
        ModuleBundleItem::create([
            'module_bundle_id' => $bundle->id,
            'module_id' => $module->id,
        ]);

        try {
            ModuleBundleItem::create([
                'module_bundle_id' => $bundle->id,
                'module_id' => $module->id,
            ]);
            $this->fail('Duplicate bundle item should be rejected by the database.');
        } catch (QueryException) {
            $this->assertDatabaseCount('module_bundle_items', 1);
        }

        $bundle->delete();

        $this->assertDatabaseCount('module_bundle_items', 0);
        $this->assertDatabaseHas('modules', ['id' => $module->id]);
    }

    public function test_subscription_command_expires_subscription_modules(): void
    {
        [$org] = $this->tenantAdmin();
        $this->module('CRM', ['CORE']);
        Subscription::create([
            'pdam_org_id' => $org->id,
            'plan_tier' => 'modular',
            'start_date' => now()->subYear(),
            'end_date' => now()->subDay(),
            'status' => 'active',
            'billing_cycle' => 'yearly',
        ]);
        SubscriptionModule::create([
            'pdam_org_id' => $org->id,
            'module_code' => 'CRM',
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('pdam:subscription-check')->assertSuccessful();

        $this->assertDatabaseHas('subscriptions', [
            'pdam_org_id' => $org->id,
            'status' => 'expired',
        ]);
        $this->assertDatabaseHas('subscription_modules', [
            'pdam_org_id' => $org->id,
            'module_code' => 'CRM',
            'status' => 'expired',
        ]);
    }

    private function tenantAdmin(): array
    {
        $org = PdamOrganization::create([
            'code' => 'audit-'.uniqid(),
            'name' => 'PDAM Audit',
            'subscription_status' => 'active',
        ]);
        $admin = User::create([
            'pdam_org_id' => $org->id,
            'name' => 'Admin Audit',
            'email' => uniqid().'@audit.test',
            'password' => 'password',
            'is_tenant_admin' => true,
            'is_active' => true,
        ]);

        return [$org, $admin];
    }

    private function module(string $code, array $dependencies = [], bool $default = false): Module
    {
        return Module::create([
            'code' => $code,
            'name' => $code,
            'dependencies' => $dependencies,
            'base_price_year' => 1000000,
            'is_default' => $default,
            'is_active' => true,
        ]);
    }

    private function platformAdmin(): PlatformAdmin
    {
        return PlatformAdmin::create([
            'email' => uniqid().'@platform.test',
            'full_name' => 'Platform Audit',
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
