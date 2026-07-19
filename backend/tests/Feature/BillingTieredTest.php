<?php

namespace Tests\Feature;

use App\Models\PdamOrganization;
use App\Models\TariffCategory;
use App\Services\BillingService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifikasi kalkulasi tarif tiered ("ember bertingkat", PRD 10.1).
 * Contoh acuan: golongan 2A3, pemakaian 35 m³ → biaya air Rp 180.000.
 */
class BillingTieredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new PdamOrganization)->forceFill(['id' => 1, 'code' => 'test', 'name' => 'Test PDAM'])->save();
        TenantContext::set(1);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function makeCategory2A3(): TariffCategory
    {
        $cat = TariffCategory::create([
            'pdam_org_id' => 1,
            'code' => '2A3',
            'name' => 'Rumah Tangga Permanen',
            'group_type' => 'rumah_tangga',
            'abonemen' => 0,
            'meter_maintenance_fee' => 0,
            'admin_fee' => 0,
            'minimum_usage_m3' => 0,
        ]);

        foreach ([[1, 0, 10, 3200], [2, 10, 20, 5500], [3, 20, null, 6200]] as [$order, $min, $max, $price]) {
            $cat->tiers()->create([
                'pdam_org_id' => 1,
                'tier_order' => $order,
                'min_usage' => $min,
                'max_usage' => $max,
                'price_per_m3' => $price,
            ]);
        }

        return $cat;
    }

    public function test_tiered_35m3_2_a3_equals_180000(): void
    {
        $service = app(BillingService::class);
        $calc = $service->calculate($this->makeCategory2A3(), 35);

        // Tier 1: 10×3200=32.000, Tier 2: 10×5500=55.000, Tier 3: 15×6200=93.000
        $this->assertSame(180000.0, $calc['water_charge']);
        // Pastikan BUKAN flat rate (35×6200 = 217.000)
        $this->assertNotSame(217000.0, $calc['water_charge']);
    }

    public function test_tiered_only_first_tier_for_low_usage(): void
    {
        $service = app(BillingService::class);
        $calc = $service->calculate($this->makeCategory2A3(), 8);

        // 8 m³ semua di tier 1: 8×3200 = 25.600
        $this->assertSame(25600.0, $calc['water_charge']);
    }

    public function test_fixed_components_added_to_total(): void
    {
        $cat = $this->makeCategory2A3();
        $cat->update(['abonemen' => 5000, 'meter_maintenance_fee' => 2500, 'admin_fee' => 2500]);

        $service = app(BillingService::class);
        $calc = $service->calculate($cat->fresh(), 35);

        // 180.000 air + 10.000 komponen tetap = 190.000
        $this->assertSame(190000.0, $calc['total']);
    }
}
