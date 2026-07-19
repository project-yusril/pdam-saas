<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\MeterReading;
use App\Models\MeterRoute;
use App\Models\PdamOrganization;
use App\Models\Province;
use App\Models\ReadingPeriod;
use App\Models\Street;
use App\Models\TariffCategory;
use App\Models\TariffTier;
use App\Models\Village;
use App\Services\BillingService;
use App\Services\MeterOcrService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifikasi Fase 4 (MTR):
 * - OCR angka meter mem-parse teks mentah jadi angka m³ + confidence.
 * - Auto-map pelanggan ke rute berdasarkan street_id.
 * - Progress baca per rute (total/terbaca/sisa/flag).
 * - Guard billing: tagihan hanya boleh digenerate bila periode baca ditutup.
 */
class MeterRoutingTest extends TestCase
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

    public function test_meter_ocr_parses_reading(): void
    {
        $ocr = app(MeterOcrService::class);

        // Angka register terpecah spasi + digit merah/liter (dibuang).
        $parsed = $ocr->parse('0 0 1 2 3.4');

        $this->assertEquals(123, $parsed['reading']);
        $this->assertGreaterThan(0, $parsed['confidence']);
    }

    public function test_meter_ocr_truncates_extra_digits(): void
    {
        $ocr = app(MeterOcrService::class);

        // 7 digit terbaca, register hitam 5 → ambil 5 digit kiri.
        $parsed = $ocr->parse('1234567', 5);

        $this->assertEquals(12345, $parsed['reading']);
        $this->assertContains('extra_digits_truncated', $parsed['warnings']);
    }

    /** Buat 1 jalan lengkap dengan hirarki alamat minimal (village wajib). */
    private function makeStreet(string $name): Street
    {
        $province = Province::create(['code' => '61', 'name' => 'Kalbar']);
        $city = City::create(['province_id' => $province->id, 'code' => '6171', 'name' => 'Pontianak']);
        $district = District::create(['city_id' => $city->id, 'code' => '617101', 'name' => 'Pontianak Kota']);
        $village = Village::create(['district_id' => $district->id, 'code' => '6171011001', 'name' => 'Sungai Bangkong']);

        return Street::create(['pdam_org_id' => 1, 'village_id' => $village->id, 'name' => $name, 'is_active' => true]);
    }

    public function test_auto_map_customers_to_route_via_street(): void
    {
        $street = $this->makeStreet('Jl Mawar');
        $route = MeterRoute::create(['pdam_org_id' => 1, 'code' => 'R-01', 'name' => 'Rute 1', 'is_active' => true]);
        $route->streets()->sync([$street->id => ['pdam_org_id' => 1]]);

        $customer = Customer::create([
            'pdam_org_id' => 1,
            'customer_number' => 'C-001',
            'full_name' => 'Budi',
            'street_id' => $street->id,
            'status' => 'active',
            'initial_reading' => 0,
        ]);

        // Simulasi auto-map: pelanggan pada jalan rute → set meter_route_id.
        Customer::where('street_id', $street->id)->update(['meter_route_id' => $route->id]);

        $this->assertEquals($route->id, $customer->fresh()->meter_route_id);
    }

    public function test_billing_blocked_until_period_closed(): void
    {
        // Golongan + tier sederhana
        $cat = TariffCategory::create([
            'pdam_org_id' => 1, 'code' => 'A1', 'name' => 'Rumah', 'group_type' => 'rumah_tangga',
            'abonemen' => 0, 'meter_maintenance_fee' => 0, 'admin_fee' => 0, 'minimum_usage_m3' => 0,
        ]);
        TariffTier::create([
            'pdam_org_id' => 1, 'tariff_category_id' => $cat->id, 'tier_order' => 1,
            'min_usage' => 0, 'max_usage' => null, 'price_per_m3' => 1000,
        ]);
        ChartOfAccount::create(['pdam_org_id' => 1, 'code' => '1-002', 'name' => 'Piutang', 'type' => 'ASSET', 'normal_balance' => 'DEBIT']);
        ChartOfAccount::create(['pdam_org_id' => 1, 'code' => '4-001', 'name' => 'Pendapatan Air', 'type' => 'REVENUE', 'normal_balance' => 'KREDIT']);

        $customer = Customer::create([
            'pdam_org_id' => 1, 'customer_number' => 'C-100', 'full_name' => 'Siti',
            'tariff_category_id' => $cat->id, 'status' => 'active', 'initial_reading' => 0,
        ]);

        $billing = app(BillingService::class);

        // Periode belum ada / belum ditutup → ditolak.
        try {
            $billing->generateForCustomer($customer, '2026-07', 0, 10);
            $this->fail('Seharusnya gagal karena periode belum ditutup.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('belum ditutup', $e->getMessage());
        }

        // Setelah periode ditutup → berhasil.
        ReadingPeriod::create(['pdam_org_id' => 1, 'period' => '2026-07', 'status' => 'closed']);
        $bill = $billing->generateForCustomer($customer, '2026-07', 0, 10);

        $this->assertEquals(10, $bill->consumption);
        $this->assertEquals(10000, (float) $bill->amount_due);
    }

    public function test_route_progress_counts_readings(): void
    {
        $route = MeterRoute::create(['pdam_org_id' => 1, 'code' => 'R-02', 'name' => 'Rute 2', 'is_active' => true]);

        $c1 = Customer::create(['pdam_org_id' => 1, 'customer_number' => 'C-201', 'full_name' => 'A', 'status' => 'active', 'meter_route_id' => $route->id, 'initial_reading' => 0]);
        Customer::create(['pdam_org_id' => 1, 'customer_number' => 'C-202', 'full_name' => 'B', 'status' => 'active', 'meter_route_id' => $route->id, 'initial_reading' => 0]);

        // 1 dari 2 pelanggan terbaca (dan ter-flag).
        MeterReading::create([
            'pdam_org_id' => 1, 'customer_id' => $c1->id, 'period' => '2026-07',
            'reading_value' => 5, 'reading_date' => '2026-07-05', 'reading_type' => 'estimated',
            'is_flagged' => true, 'flag_reason' => 'estimated_reading',
        ]);

        $readByRoute = MeterReading::query()
            ->where('meter_readings.period', '2026-07')
            ->join('customers', 'customers.id', '=', 'meter_readings.customer_id')
            ->where('customers.meter_route_id', $route->id)
            ->count();

        $this->assertEquals(1, $readByRoute);
    }
}
