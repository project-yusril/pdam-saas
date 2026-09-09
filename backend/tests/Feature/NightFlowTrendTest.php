<?php

namespace Tests\Feature;

use App\Models\DistributionReading;
use App\Models\DmaZone;
use App\Models\Module;
use App\Models\NrwBalance;
use App\Models\PdamOrganization;
use App\Models\SubscriptionModule;
use App\Models\User;
use App\Services\Geo\NrwAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * #3 MNF debit malam (02:00–04:00) per DMA vs baseline + #4 tren NRW
 * (nrw_balances tersimpan) + cron pdam:nrw-monthly.
 * Sumber data: distribution_readings JAM-JAMAN yang sudah ada.
 */
class NightFlowTrendTest extends TestCase
{
    use RefreshDatabase;

    private int $orgId;

    private User $admin;

    private DmaZone $dma;

    protected function setUp(): void
    {
        parent::setUp();

        $org = PdamOrganization::create(['code' => 'mnf', 'name' => 'MNF PDAM', 'subscription_status' => 'active']);
        $this->orgId = $org->id;
        $this->admin = User::create([
            'pdam_org_id' => $org->id, 'name' => 'Geo Admin', 'email' => 'mnf@test.local',
            'password' => 'password', 'is_tenant_admin' => true, 'is_active' => true,
        ]);
        Module::firstOrCreate(['code' => 'GIS'], ['name' => 'GIS', 'is_active' => true]);
        SubscriptionModule::create(['pdam_org_id' => $org->id, 'module_code' => 'GIS', 'status' => 'active']);

        $this->dma = DmaZone::create([
            'pdam_org_id' => $org->id, 'code' => 'DMA-M', 'name' => 'DMA Malam',
            'boundary' => ['type' => 'Polygon', 'coordinates' => [[[109.0, 1.0], [109.1, 1.1], [109.1, 1.0], [109.0, 1.0]]]],
            'total_connections' => 100, 'base_demand_m3day' => 1200, 'is_active' => true,
        ]);
    }

    private function reading(\DateTimeInterface $at, float $flow): void
    {
        DistributionReading::create([
            'pdam_org_id' => $this->orgId, 'dma_zone_id' => $this->dma->id,
            'reading_at' => $at, 'flow_rate_m3h' => $flow,
        ]);
    }

    // ── MNF ────────────────────────────────────────────────────────────────

    public function test_mnf_only_counts_night_window_and_flags_waspada(): void
    {
        $this->reading(now()->subDay()->setTime(2, 30), 10);
        $this->reading(now()->subDay()->setTime(3, 15), 10);
        $this->reading(now()->subDay()->setTime(10, 0), 99);   // siang TIDAK boleh ikut
        $this->reading(now()->subDays(2)->setTime(3, 5), 10);               // 02:00 kemarin ✓

        $json = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/admin/network/mnf.json')->assertOk()->json();

        $this->assertCount(1, $json['items']);
        $m = $json['items'][0];
        $this->assertSame('DMA-M', $m['code']);
        $this->assertSame(3, $m['n_samples']);
        $this->assertEqualsWithDelta(10, $m['mnf_m3h'], 0.01);
        $this->assertEqualsWithDelta(240, $m['mnf_m3day'], 0.1);
        $this->assertEqualsWithDelta(20, $m['pct_of_base'], 0.1);     // 240/1200
        $this->assertSame('baseline_dma', $m['base_source']);
        $this->assertSame(15, (int) $m['alert_pct']);
        $this->assertSame('waspada', $m['status']);                   // >15 dan ≤30
    }

    public function test_mnf_red_flag_when_exceeds_double_threshold(): void
    {
        $this->reading(now()->subDay()->setTime(2, 0), 80);
        $this->reading(now()->subDay()->setTime(3, 0), 80);
        $res = app(NrwAnalysisService::class)->nightFlowAnalysis();
        $this->assertSame('merah', $res[0]['status']);    // 1920/1200 = 160% > 30
        $this->assertEqualsWithDelta(160, $res[0]['pct_of_base'], 0.1);

        // endpoint ikut menampilkan merah utk pewarnaan polygon
        $json = $this->actingAs($this->admin, 'sanctum')->getJson('/admin/network/mnf.json')->assertOk()->json();
        $this->assertSame('merah', $json['items'][0]['status']);
    }

    public function test_mnf_fallback_baseline_from_connections_when_not_set(): void
    {
        $this->dma->update(['base_demand_m3day' => null]);
        $this->reading(now()->subDay()->setTime(2, 0), 10);

        $res = app(NrwAnalysisService::class)->nightFlowAnalysis();
        $this->assertSame('estimasi_koneksi', $res[0]['base_source']);      // 0.8 m³/koneksi/hari
        $this->assertEqualsWithDelta(80, $res[0]['base_m3day'], 0.01);       // 100 × 0.8
        $this->assertEqualsWithDelta(300, $res[0]['pct_of_base'], 0.1);
        $this->assertEqualsWithDelta(100, $res[0]['per_conn_lph'], 0.5);     // 10 m³h/100conn → 100 l/h
        $this->assertSame('merah', $res[0]['status']);
    }

    public function test_mnf_without_data_marked_nihil(): void
    {
        $res = app(NrwAnalysisService::class)->nightFlowAnalysis();
        $this->assertSame(0, $res[0]['n_samples']);
        $this->assertNull($res[0]['mnf_m3h']);
        $this->assertSame('belum_data', $res[0]['status']);
    }

    // ── tren NRW ───────────────────────────────────────────────────────────

    public function test_trend_returns_stored_balances_sorted_and_trimmed(): void
    {
        $mkBal = function (string $period, float $nrw) {
            NrwBalance::create([
                'pdam_org_id' => $this->orgId, 'dma_zone_id' => $this->dma->id, 'period' => $period,
                'system_input_m3' => 10000, 'billed_metered_m3' => 7000,
                'authorised_consumption_m3' => 7000, 'water_losses_m3' => 3000,
                'nrw_percentage' => $nrw, 'ili' => 3,
            ]);
        };
        foreach (['2020-01' => 40.0, '2020-02' => 35.5, '2020-03' => 31.25] as $p => $n) {
            $mkBal($p, $n);
        }
        for ($i = 4; $i <= 12; $i++) {
            $mkBal(sprintf('2020-%02d', $i), 20);   // biar >= 6 bulan ada juga
        }

        $json = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/admin/network/nrw-trend.json?months=3')->assertOk()->json();

        $dmaRow = $json['dmas'][0];
        $this->assertSame('DMA-M', $dmaRow['code']);
        $this->assertCount(3, $dmaRow['periods'], 'hanya 3 periode terbaru');
        $this->assertSame('2020-10', $dmaRow['periods'][0]['period']);
        $this->assertSame('2020-12', $dmaRow['periods'][2]['period']);
        $this->assertEquals(20.0, $dmaRow['periods'][2]['nrw_percentage']);
        $this->assertEquals(3000.0, $dmaRow['periods'][2]['water_losses_m3']);
    }

    public function test_trend_filters_single_dma(): void
    {
        $other = DmaZone::create(['pdam_org_id' => $this->orgId, 'code' => 'DMA-O', 'name' => 'Lain', 'boundary' => [], 'is_active' => true]);
        NrwBalance::create(['pdam_org_id' => $this->orgId, 'dma_zone_id' => $this->dma->id, 'period' => '2021-01',
            'system_input_m3' => 100, 'billed_metered_m3' => 80, 'authorised_consumption_m3' => 80, 'water_losses_m3' => 20, 'nrw_percentage' => 20]);
        NrwBalance::create(['pdam_org_id' => $this->orgId, 'dma_zone_id' => $other->id, 'period' => '2021-01',
            'system_input_m3' => 100, 'billed_metered_m3' => 60, 'authorised_consumption_m3' => 60, 'water_losses_m3' => 40, 'nrw_percentage' => 40]);

        $json = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/admin/network/nrw-trend.json?dma_id='.$this->dma->id)->assertOk()->json();
        $this->assertCount(1, $json['dmas']);
        $this->assertSame($this->dma->id, $json['dmas'][0]['dma_id']);
    }

    // ── cron bulanan ───────────────────────────────────────────────────────

    public function test_nrw_monthly_command_fills_previous_month(): void
    {
        $period = now()->startOfMonth()->subMonthNoOverflow()->format('Y-m');
        $start = Carbon::parse($period.'-01');
        DistributionReading::create([
            'pdam_org_id' => $this->orgId, 'dma_zone_id' => $this->dma->id,
            'reading_at' => $start->copy()->addDay()->setTime(5, 0), 'flow_rate_m3h' => 10,
        ]);
        DistributionReading::create([
            'pdam_org_id' => $this->orgId, 'dma_zone_id' => $this->dma->id,
            'reading_at' => $start->copy()->addDays(5)->setTime(13, 0), 'flow_rate_m3h' => 30,
        ]);

        $this->artisan('pdam:nrw-monthly')->assertSuccessful();

        $bal = NrwBalance::query()->where('dma_zone_id', $this->dma->id)->where('period', $period)->first();
        $this->assertNotNull($bal, 'balance bulan lalu harus tersimpan otomatis');
        $days = (int) $start->daysInMonth;
        $this->assertEqualsWithDelta(((10 + 30) / 2) * 24 * $days, (float) $bal->system_input_m3, 1);
        $this->assertNotNull($bal->nrw_percentage);
    }

    public function test_nrw_monthly_skips_dma_without_readings(): void
    {
        $this->artisan('pdam:nrw-monthly')->assertSuccessful();
        $this->assertSame(0, NrwBalance::query()->count());
    }

    public function test_nrw_monthly_multi_tenant_isolation(): void
    {
        // org 2 dengan DMA sendiri; hanya org 1 punya reading → hanya balance org 1
        $org2 = PdamOrganization::create(['code' => 'mnf2', 'name' => 'MNF2 PDAM', 'subscription_status' => 'active']);
        DmaZone::create(['pdam_org_id' => $org2->id, 'code' => 'DMA-X2', 'name' => 'X2', 'boundary' => [], 'is_active' => true, 'total_connections' => 5, 'base_demand_m3day' => 10]);

        $period = now()->startOfMonth()->subMonthNoOverflow()->format('Y-m');
        DistributionReading::create([
            'pdam_org_id' => $this->orgId, 'dma_zone_id' => $this->dma->id,
            'reading_at' => now()->startOfMonth()->subMonthNoOverflow()->addDay(), 'flow_rate_m3h' => 12,
        ]);

        $this->artisan('pdam:nrw-monthly')->assertSuccessful();

        $rows = NrwBalance::query()->get();
        $this->assertCount(1, $rows, 'org tanpa reading tidak bikin balance');
        $this->assertSame($this->orgId, $rows[0]->pdam_org_id);
        $this->assertSame($period, $rows[0]->period);
    }

    public function test_invalid_period_argument_fails(): void
    {
        $this->artisan('pdam:nrw-monthly', ['period' => '2026-8'])->assertFailed();
    }
}
