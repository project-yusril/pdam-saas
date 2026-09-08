<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Module;
use App\Models\PdamOrganization;
use App\Models\Permission;
use App\Models\ReadingPeriod;
use App\Models\Role;
use App\Models\SubscriptionModule;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PRD §23 "digit meter" kini punya konsumen: validasi input pembacaan & default OCR
 * memakai config('business.meter.digits') (0 = fallback aman register 5 digit -> 99999).
 */
class MeterDigitValidationTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private User $admin;

    private int $orgId;

    protected function setUp(): void
    {
        parent::setUp();
        $org = PdamOrganization::create(['code' => 'mdig'.uniqid(), 'name' => 'Meter Digit', 'subscription_status' => 'active']);
        $this->orgId = $org->id;
        TenantContext::set($org->id);

        Module::firstOrCreate(['code' => 'MTR'], ['name' => 'Baca Meter', 'is_active' => true]);
        SubscriptionModule::firstOrCreate(
            ['pdam_org_id' => $this->orgId, 'module_code' => 'MTR'],
            ['status' => 'active'],
        );

        $this->admin = User::create([
            'pdam_org_id' => $this->orgId,
            'name' => 'petugas',
            'email' => 'petugas'.uniqid().'@md.local',
            'password' => 'password',
            'is_tenant_admin' => true,
            'is_active' => true,
        ]);
        $perm = Permission::firstOrCreate(['code' => 'mtr.reading.create'], [
            'module_code' => 'MTR', 'resource' => 'reading', 'action' => 'create',
        ]);
        $role = Role::create(['code' => 'petugas-md', 'pdam_org_id' => $this->orgId, 'name' => 'Petugas MD']);
        $role->permissions()->attach($perm->id);
        $this->admin->roles()->attach($role);
        $this->admin->unsetRelation('roles')->unsetRelation('permissions');

        $this->customer = Customer::create([
            'pdam_org_id' => $this->orgId,
            'customer_number' => 'MD-'.uniqid(),
            'full_name' => 'A',
            'status' => 'active',
        ]);
    }

    private function startPeriod(): void
    {
        ReadingPeriod::query()->updateOrCreate(
            ['pdam_org_id' => $this->orgId, 'period' => '2026-09'],
            ['status' => 'open', 'opened_at' => now()],
        );
    }

    public function test_fallback_five_digit_when_unset(): void
    {
        $this->startPeriod();

        // config default 0 -> fallback 5 digit (max 99.999): reading >99999 harus 422
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/meter-readings', [
            'customer_id' => $this->customer->id,
            'period' => '2026-09',
            'reading_value' => 123456,
            'reading_date' => '2026-09-01',
            'reading_type' => 'manual_corrected',
        ])->assertStatus(422);

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/meter-readings', [
            'customer_id' => $this->customer->id,
            'period' => '2026-09',
            'reading_value' => 99999,
            'reading_date' => '2026-09-01',
            'reading_type' => 'manual_corrected',
        ])->assertCreated();
    }

    public function test_three_digit_register_bounds_value(): void
    {
        config(['business.meter.digits' => 3]);
        $this->startPeriod();

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/meter-readings', [
            'customer_id' => $this->customer->id,
            'period' => '2026-09',
            'reading_value' => 1000,
            'reading_date' => '2026-09-01',
            'reading_type' => 'manual_corrected',
        ])->assertStatus(422);

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/meter-readings', [
            'customer_id' => $this->customer->id,
            'period' => '2026-09',
            'reading_value' => 42,
            'reading_date' => '2026-09-01',
            'reading_type' => 'manual_corrected',
        ])->assertCreated();
    }

    public function test_parse_default_black_digits_from_config(): void
    {
        config(['business.meter.digits' => 6]);

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/meter-readings/parse', [
            'raw_text' => '000123.4',
        ])->assertOk()->assertJsonPath('data.reading', 123);
    }
}
