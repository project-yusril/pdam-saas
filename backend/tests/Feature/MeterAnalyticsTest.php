<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Meter;
use App\Models\MeterAnomaly;
use App\Models\MeterLifecycleEvent;
use App\Models\MeterReading;
use App\Models\PdamOrganization;
use App\Models\TariffCategory;
use App\Services\AnomalyDetectionService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifikasi Fase 6 (METX):
 * - Engine deteksi anomali rule-based (spike, drop, zero_streak, category_mismatch).
 * - Idempotensi: scan ulang tak menggandakan anomali.
 * - Lifecycle meter (pasang → cabut) mencatat event & mengubah status.
 */
class MeterAnalyticsTest extends TestCase
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

    /** Buat pelanggan dengan deret pembacaan konsumsi tertentu (m³ per periode). */
    private function makeCustomerWithConsumption(array $consumptions, ?int $categoryId = null): Customer
    {
        $customer = Customer::create([
            'pdam_org_id' => 1,
            'customer_number' => 'C-'.uniqid(),
            'full_name' => 'Uji',
            'status' => 'active',
            'initial_reading' => 0,
            'tariff_category_id' => $categoryId,
        ]);

        $cumulative = 0;
        $year = 2026;
        $month = 1;
        foreach ($consumptions as $c) {
            $cumulative += $c;
            MeterReading::create([
                'pdam_org_id' => 1,
                'customer_id' => $customer->id,
                'period' => sprintf('%04d-%02d', $year, $month),
                'reading_value' => $cumulative,
                'reading_date' => sprintf('%04d-%02d-05', $year, $month),
                'reading_type' => 'ocr_confirmed',
            ]);
            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
        }

        return $customer;
    }

    public function test_detects_spike_anomaly(): void
    {
        // 3 bulan @10 m³ lalu lonjakan 50 m³ (>2× rata-rata 10).
        $customer = $this->makeCustomerWithConsumption([10, 10, 10, 50]);

        $service = app(AnomalyDetectionService::class);
        $created = $service->scanCustomer($customer, '2026-04');

        $this->assertGreaterThanOrEqual(1, $created);
        $this->assertDatabaseHas('meter_anomalies', [
            'customer_id' => $customer->id,
            'period' => '2026-04',
            'rule_code' => 'spike',
        ]);
    }

    public function test_detects_zero_streak(): void
    {
        // 2 bulan pakai lalu 0,0 beruntun.
        $customer = $this->makeCustomerWithConsumption([12, 12, 0, 0]);

        $service = app(AnomalyDetectionService::class);
        $service->scanCustomer($customer, '2026-04');

        $this->assertDatabaseHas('meter_anomalies', [
            'customer_id' => $customer->id,
            'period' => '2026-04',
            'rule_code' => 'zero_streak',
        ]);
    }

    public function test_scan_is_idempotent(): void
    {
        $customer = $this->makeCustomerWithConsumption([10, 10, 10, 50]);
        $service = app(AnomalyDetectionService::class);

        $service->scanCustomer($customer, '2026-04');
        $countAfterFirst = MeterAnomaly::where('customer_id', $customer->id)->count();

        // Scan ulang tidak menambah baris (updateOrCreate).
        $service->scanCustomer($customer, '2026-04');
        $countAfterSecond = MeterAnomaly::where('customer_id', $customer->id)->count();

        $this->assertEquals($countAfterFirst, $countAfterSecond);
    }

    public function test_category_mismatch_for_household(): void
    {
        $cat = TariffCategory::create([
            'pdam_org_id' => 1, 'code' => 'RT1', 'name' => 'Rumah Tangga', 'group_type' => 'rumah_tangga',
            'abonemen' => 0, 'meter_maintenance_fee' => 0, 'admin_fee' => 0, 'minimum_usage_m3' => 0,
        ]);

        // Rumah tangga tapi konsumsi 150 m³ (> ambang 100).
        $customer = $this->makeCustomerWithConsumption([150], $cat->id);

        $service = app(AnomalyDetectionService::class);
        $service->scanCustomer($customer, '2026-01');

        $this->assertDatabaseHas('meter_anomalies', [
            'customer_id' => $customer->id,
            'rule_code' => 'category_mismatch',
        ]);
    }

    public function test_meter_lifecycle_install_and_remove(): void
    {
        $meter = Meter::create([
            'pdam_org_id' => 1, 'serial_number' => 'MTR-001', 'status' => 'gudang', 'condition' => 'baru',
        ]);
        $customer = Customer::create([
            'pdam_org_id' => 1, 'customer_number' => 'C-900', 'full_name' => 'Pasang', 'status' => 'active', 'initial_reading' => 0,
        ]);

        // Pasang.
        $meter->update(['status' => 'terpasang', 'customer_id' => $customer->id]);
        MeterLifecycleEvent::create([
            'pdam_org_id' => 1, 'meter_id' => $meter->id, 'event' => 'installed',
            'from_status' => 'gudang', 'to_status' => 'terpasang', 'customer_id' => $customer->id,
        ]);

        $this->assertEquals('terpasang', $meter->fresh()->status);
        $this->assertDatabaseHas('meter_lifecycle_events', [
            'meter_id' => $meter->id, 'event' => 'installed',
        ]);

        // Cabut.
        $meter->update(['status' => 'dicabut', 'customer_id' => null]);
        $this->assertNull($meter->fresh()->customer_id);
    }
}
