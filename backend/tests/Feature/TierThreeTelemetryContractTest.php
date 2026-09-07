<?php

namespace Tests\Feature;

use App\Models\DmaZone;
use App\Models\DistributionReading;
use App\Models\Module;
use App\Models\PdamOrganization;
use App\Models\ProductionLog;
use App\Models\SensorReading;
use App\Models\SubscriptionModule;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kontrak Tier-3 (IOT/PROD/DIST): bukti API ingest/dashboard siap untuk
 * perangkat nyata (hardware menyusul) + dipakai simulator ops/simulators.
 */
class TierThreeTelemetryContractTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $headers = 'application/json';

    protected function setUp(): void
    {
        parent::setUp();

        $org = PdamOrganization::create([
            'code' => 'tier3'.uniqid(),
            'name' => 'PDAM Tier3 Test',
            'subscription_status' => 'active',
        ]);
        TenantContext::set($org->id);

        $this->user = User::create([
            'pdam_org_id' => $org->id,
            'name' => 'SCADA Op',
            'email' => uniqid().'@pdam.test',
            'password' => 'password',
            'is_tenant_admin' => true,
            'is_active' => true,
        ]);

        foreach (['IOT', 'PROD', 'DIST', 'NRW'] as $code) {
            Module::query()->firstOrCreate(['code' => $code], ['name' => $code, 'is_active' => true]);
            SubscriptionModule::query()->create([
                'pdam_org_id' => $org->id,
                'module_code' => $code,
                'status' => 'active',
            ]);
        }

        DmaZone::query()->create([
            'pdam_org_id' => $org->id,
            'code' => 'DMA1',
            'name' => 'DMA Test Satu',
            'total_connections' => 1234,
        ]);
    }

    public function test_iot_ingest_accepts_device_batch(): void
    {
        $payload = ['readings' => [
            ['device_id' => 'MTR-A1', 'value' => 128.4, 'flow_rate' => 2.1, 'reading_at' => now()->toIso8601String(), 'source' => 'lorawan'],
            ['device_id' => 'MTR-A2', 'value' => 99.9, 'flow_rate' => 1.5, 'reading_at' => now()->subMinutes(30)->toIso8601String(), 'battery' => 18, 'signal_strength' => -95],
        ]];

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/iot/ingest', $payload, ['Accept' => $this->headers])
            ->assertOk()
            ->assertJsonPath('data.ingested', 2);

        $this->assertSame(2, SensorReading::count());
        $dash = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/iot/dashboard')
            ->assertOk();
        $dash->assertJsonPath('data.alerts.0.alert_type', 'low_battery');
        $dash->assertJsonStructure(['data' => ['total_devices', 'devices', 'alerts']]);
    }

    public function test_iot_ingest_requires_device_and_value(): void
    {
        $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/iot/ingest', [
            'readings' => [['reading_at' => now()->toIso8601String()]],
        ])->assertStatus(422);
    }

    public function test_production_ingest_persists_water_balance_fields(): void
    {
        $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/production/ingest', [
            'readings' => [[
                'production_date' => now()->toDateString(),
                'raw_water_m3' => 12000,
                'treated_water_m3' => 11200,
                'distributed_water_m3' => 10100,
                'pump_runtime_hours' => 84,
                'power_consumption_kwh' => 3600,
                'turbidity_ntu' => .8,
                'ph' => 7.1,
                'chlorine_residual' => .4,
            ]],
        ])->assertOk()->assertJsonPath('data.ingested', 1);

        $this->assertSame(11200, (int) ProductionLog::first()->treated_water_m3);
        $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/production/dashboard')->assertOk();
    }

    public function test_distribution_ingest_requires_existing_dma_but_accepts_valid_batch(): void
    {
        $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/distribution/ingest', [
            'readings' => [['dma_zone_id' => 9999, 'reading_at' => now()->toIso8601String()]],
        ])->assertStatus(422);

        $dmaId = DmaZone::first()->id;
        $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/distribution/ingest', [
            'readings' => [
                ['dma_zone_id' => $dmaId, 'reading_at' => now()->toIso8601String(), 'flow_rate_m3h' => 410.2, 'pressure_bar' => 2.6],
                ['dma_zone_id' => $dmaId, 'reading_at' => now()->subHours(6)->toIso8601String(), 'flow_rate_m3h' => 388.0, 'pressure_bar' => 2.4],
            ],
        ])->assertOk()->assertJsonPath('data.ingested', 2);

        $this->assertSame(2, DistributionReading::count());
        $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/distribution/dma-dashboard')
            ->assertOk()->assertJsonStructure(['data']);
    }
}
