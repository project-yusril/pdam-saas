<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\PdamOrganization;
use App\Models\PlatformAdmin;
use App\Models\Subscription;
use App\Models\SubscriptionModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * PRD §23 "durasi trial" kini punya konsumen nyata (gated):
 * Super-Admin aktivasi manual modul tier>0 → expires_at = trial_days (config),
 * billing cycle `trial`, bukan aktivasi paid penuh 1 tahun.
 */
class TenantTrialActivationTest extends TestCase
{
    use RefreshDatabase;

    private function platform(): PlatformAdmin
    {
        return PlatformAdmin::create(['email' => 'p+'.uniqid().'@t.test', 'full_name' => 'SA', 'password' => 'password', 'is_active' => true]);
    }

    public function test_trial_days_zero_means_no_auto_trial(): void
    {
        config(['business.billing.trial_days' => 0]);
        $org = PdamOrganization::create(['code' => 'tr1'.uniqid(), 'name' => 'T', 'subscription_status' => 'prospect']);
        $mod = Module::create(['code' => 'T1'.uniqid(), 'name' => 't', 'base_price_year' => 100, 'tier' => 1, 'is_default' => false, 'is_active' => true]);
        SubscriptionModule::create(['pdam_org_id' => $org->id, 'module_code' => $mod->code, 'status' => 'locked']);

        Sanctum::actingAs($this->platform(), ['platform']);
        $this->postJson('/api/v1/platform/tenants/'.$org->id.'/modules/'.$mod->code.'/activate')
            ->assertOk()
            ->assertJsonPath('data.activation_method', 'manual_superadmin')
            ->assertJsonPath('data.expires_at', null);

        $this->assertSame(0, Subscription::query()->withoutGlobalScopes()->count(), 'tanpa trial tidak membuat subscription');
    }

    public function test_trial_days_set_expires_in_future_and_trial_row(): void
    {
        $orgId = 'trial'.uniqid();
        config(['business.billing.trial_days' => 7]);
        $org = PdamOrganization::create(['code' => $orgId, 'name' => $orgId, 'subscription_status' => 'prospect']);
        $mod = Module::create(['code' => 'TRIAL-'.strtoupper(substr(uniqid(), -4)), 'name' => 'Coba', 'base_price_year' => 200, 'tier' => 2, 'is_default' => false, 'is_active' => true]);
        SubscriptionModule::create(['pdam_org_id' => $org->id, 'module_code' => $mod->code, 'status' => 'locked']);

        Sanctum::actingAs($this->platform(), ['platform']);
        $this->postJson('/api/v1/platform/tenants/'.$org->id.'/modules/'.$mod->code.'/activate')
            ->assertOk()
            ->assertJsonPath('data.activation_method', 'trial')
            ->assertJsonPath('data.status', 'active');

        $exp = SubscriptionModule::withoutGlobalScopes()->where('module_code', $mod->code)->sole()->expires_at;
        $this->assertTrue($exp->isFuture() && (int) now()->diffInDays($exp) <= 8);

        $sub = Subscription::query()->withoutGlobalScopes()->where('pdam_org_id', $org->id)->sole();
        $this->assertSame('trial', $sub->billing_cycle);
        $this->assertSame('active', $sub->status);
        $this->assertTrue($sub->end_date->isFuture());
    }

    public function test_core_default_module_does_not_auto_trial_even_if_days_set(): void
    {
        config(['business.billing.trial_days' => 10]);
        $org = PdamOrganization::create(['code' => 'trd'.uniqid(), 'name' => 'T', 'subscription_status' => 'prospect']);
        $mod = Module::create(['code' => 'CORE-'.uniqid(), 'name' => 'Default', 'base_price_year' => 0, 'tier' => 0, 'is_default' => true, 'is_active' => true]);
        SubscriptionModule::create(['pdam_org_id' => $org->id, 'module_code' => $mod->code, 'status' => 'locked']);

        Sanctum::actingAs($this->platform(), ['platform']);
        $r = $this->postJson('/api/v1/platform/tenants/'.$org->id.'/modules/'.$mod->code.'/activate');
        $r->assertOk();
        $this->assertNull($r->json('data.expires_at'));
        $this->assertSame('manual_superadmin', $r->json('data.activation_method'));
    }
}
