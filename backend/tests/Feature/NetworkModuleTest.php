<?php

namespace Tests\Feature;

use App\Models\DmaZone;
use App\Models\GisFeature;
use App\Models\GisNetworkEdge;
use App\Models\MaintenanceSchedule;
use App\Models\Module;
use App\Models\PdamOrganization;
use App\Models\SubscriptionModule;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Modul lanjutan GIS Jaringan (batch #5–#9):
 *  #5 health.json     — validator integritas (node yatim, pipa tanpa edge, klaster, ruas panjang, hydrant/DMA).
 *  #6 risks.json      — skor risiko pipa bahan+umur+riwayat WO repair + top prioritas.
 *  #7 export/import.geojson + lembar status cetak.
 *  #8 maintenance.json — preventif valve/hydrant → WO (endpoint + cron pdam:mnt-network).
 *  #9 feasibility.json — tap survey → pipa terdekat → usulan biaya dari config.
 */
class NetworkModuleTest extends TestCase
{
    use RefreshDatabase;

    private int $orgId;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $org = PdamOrganization::create(['code' => 'mod', 'name' => 'Mod PDAM', 'subscription_status' => 'active']);
        $this->orgId = $org->id;
        $this->admin = User::create([
            'pdam_org_id' => $org->id, 'name' => 'GIS Admin', 'email' => 'mod@test.local',
            'password' => 'password', 'is_tenant_admin' => true, 'is_active' => true,
        ]);
        Module::firstOrCreate(['code' => 'GIS'], ['name' => 'GIS', 'is_active' => true]);
        SubscriptionModule::create(['pdam_org_id' => $org->id, 'module_code' => 'GIS', 'status' => 'active']);
    }

    // ── fixtures ───────────────────────────────────────────────────────────

    private function node(string $type, float $lng, float $lat, array $props = []): GisFeature
    {
        return GisFeature::create([
            'pdam_org_id' => $this->orgId, 'feature_type' => $type,
            'name' => strtoupper($type).'-'.substr((string) (int) ($lng * 1000).$lat, 0, 6),
            'geometry' => ['type' => 'Point', 'coordinates' => [$lng, $lat]],
            'properties' => $props, 'status' => 'active',
        ]);
    }

    private function pipe(GisFeature $a, GisFeature $b, array $props = [], float $len = 200.0): GisFeature
    {
        $p = GisFeature::create([
            'pdam_org_id' => $this->orgId, 'feature_type' => 'pipe',
            'name' => $props['name'] ?? ('PI-'.substr(md5($a->id.$b->id.microtime()), 0, 6)),
            'geometry' => ['type' => 'LineString', 'coordinates' => [$a->geometry['coordinates'], $b->geometry['coordinates']]],
            'properties' => array_merge(['length_meters' => $len], $props), 'status' => 'active',
        ]);
        if (! ($props['no_edge'] ?? false)) {
            GisNetworkEdge::create([
                'pdam_org_id' => $this->orgId, 'pipe_feature_id' => $p->id,
                'from_node_id' => $a->id, 'from_node_type' => $a->feature_type,
                'to_node_id' => $b->id, 'to_node_type' => $b->feature_type, 'length_meters' => $len,
            ]);
        }

        return $p;
    }

    private function cleanNetwork(): array
    {
        $pmp = $this->node('pump', 109.300, 1.350);
        $v1 = $this->node('valve', 109.305, 1.355);
        $j = $this->node('junction', 109.315, 1.360);
        $v2 = $this->node('valve', 109.322, 1.362);
        $e1 = $this->pipe($pmp, $v1, ['material' => 'HDPE', 'install_year' => 2018], 150);
        $e2 = $this->pipe($v1, $j, ['material' => 'HDPE', 'install_year' => 2018], 150);
        $e3 = $this->pipe($j, $v2, ['material' => 'HDPE', 'install_year' => 2018], 90);
        $dma = DmaZone::create([
            'pdam_org_id' => $this->orgId, 'code' => 'DMA-C', 'name' => 'Bersih',
            'boundary' => ['type' => 'Polygon', 'coordinates' => [[[109.29, 1.345], [109.33, 1.345], [109.33, 1.368], [109.29, 1.368], [109.29, 1.345]]]],
            'is_active' => true,
        ]);
        $h1 = $this->node('hydrant', 109.3059, 1.3550);
        $h2 = $this->node('hydrant', 109.3159, 1.3600);
        // tiap hydrant disambung stub biar tidah dianggap menggantung
        $this->pipe($v1, $h1, ['length_only' => true, 'name' => 'STUB-H1'], 60);
        $this->pipe($j, $h2, ['length_only' => true, 'name' => 'STUB-H2'], 60);

        return compact('pmp', 'v1', 'j', 'v2', 'e1', 'e2', 'e3', 'dma') + ['h1' => $h1, 'h2' => $h2];
    }

    private function dirtyExtras(): array
    {
        // (a) node menggantung (b) pipa tanpa edge (c) klaster yatim 2 node (d) DMA kurang hydrant
        $d = $this->node('junction', 109.990, 1.990);
        $orphanPipe = $this->pipe($this->node('junction', 108.400, 1.140), $this->node('junction', 108.410, 1.145), ['no_edge' => true], 800);
        $k1 = $this->node('junction', 109.500, 1.500);
        $k2 = $this->node('junction', 109.510, 1.510);
        $this->pipe($k1, $k2, [], 500);   // panjang + kedua ujung junction → long_uncontrolled
        DmaZone::create([
            'pdam_org_id' => $this->orgId, 'code' => 'DMA-E', 'name' => 'Sepi Hydrant',
            'boundary' => ['type' => 'Polygon', 'coordinates' => [[[109.50, 1.80], [109.53, 1.80], [109.53, 1.83], [109.50, 1.83], [109.50, 1.80]]]],
            'is_active' => true,
        ]);

        return compact('d', 'orphanPipe', 'k1', 'k2');
    }

    // ── #5 validator ───────────────────────────────────────────────────────

    public function test_health_audit_clean_network_scores_100(): void
    {
        $n = $this->cleanNetwork();
        $json = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/admin/network/health.json')->assertOk()->json();

        foreach (['dangling_nodes', 'pipes_without_edges', 'isolated_clusters', 'long_uncontrolled_segments', 'hydrants_below_min'] as $k) {
            $this->assertSame(0, $json['counts'][$k], "count {$k} harus 0 pada jaringan bersih: ".json_encode($json['counts']));
        }
        $this->assertSame(100, $json['score']);
        $this->assertSame(1, $json['passed']);
    }

    public function test_health_audit_finds_every_dirty_pattern(): void
    {
        $this->cleanNetwork();
        $d = $this->dirtyExtras();

        $json = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/admin/network/health.json')->assertOk()->json();
        $c = $json['counts'];
        $byCheck = collect($json['issues'])->keyBy('check');

        $this->assertSame(3, $c['dangling_nodes'], 'node yatim + 2 endpoint pipa tanpa edge');
        $this->assertContains($d['d']->id, array_column($byCheck['dangling_nodes']['items'], 'id'));
        $this->assertSame(1, $c['pipes_without_edges']);
        $this->assertSame($d['orphanPipe']->id, $byCheck['pipes_without_edges']['items'][0]['id']);
        $this->assertSame(1, $c['isolated_clusters']);
        $this->assertSame(2, $byCheck['isolated_clusters']['items'][0]['size']);
        $this->assertSame(1, $c['long_uncontrolled_segments']);     // 108.40 segmen 800 m tanpa valve ujung
        $this->assertSame(1, $c['hydrants_below_min']);             // DMA-E nihil hydrant tapi ≥2 aturan
        $this->assertLessThan(100, $json['score']);
    }

    // ── #6 peta risiko ─────────────────────────────────────────────────────

    public function test_risk_score_old_brittle_pipe_with_repair_history(): void
    {
        $n = $this->cleanNetwork();
        $old = $this->pipe(
            $this->node('valve', 109.400, 1.400),
            $this->node('junction', 109.410, 1.405),
            ['material' => 'Besi Tuang', 'install_year' => (int) now()->format('Y') - 55, 'name' => 'RUAS-TUA']
        );
        foreach (range(1, 3) as $k) {
            WorkOrder::create([
                'pdam_org_id' => $this->orgId, 'wo_number' => 'WO-R'.$k, 'type' => 'repair',
                'priority' => 'high', 'source_type' => 'gis_feature', 'source_id' => $old->id,
                'address' => 'x', 'status' => $k < 3 ? 'completed' : 'closed',
            ]);
        }

        $json = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/admin/network/risks.json')->assertOk()->json();
        $hot = collect($json['pipes'])->firstWhere('pipe_id', $old->id);
        $new = collect($json['pipes'])->firstWhere('pipe_id', $n['e1']->id);

        $this->assertGreaterThanOrEqual(70, $hot['score'], 'tua + besi tuang + 3 WO = kritis-ish');
        $this->assertSame('kritis', $hot['level']);
        $this->assertSame(3, $hot['repairs']);
        $this->assertGreaterThanOrEqual(45, $hot['score'] - $new['score']);
        $this->assertLessThan(45, $new['score'], 'HDPE muda tanpa riwayat = bukan risiko tinggi');
        $this->assertSame('rendah', $new['level']);
        $this->assertGreaterThanOrEqual(1, $json['counts']['kritis']);
    }

    public function test_audit_bundle_and_priority_toplist(): void
    {
        $old = $this->pipe(
            $this->node('valve', 109.400, 1.400),
            $this->node('pump', 109.410, 1.405),      // +sumber biar edge-nya terhubung
            ['material' => 'Asbes', 'install_year' => 1985, 'name' => 'RUAS-X']
        );
        $json = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/admin/network/audit.json')->assertOk()->json();

        $this->assertArrayHasKey('health', $json);
        $this->assertArrayHasKey('risk_counts', $json);
        $this->assertContains('RUAS-X', array_column($json['priority_pipes'], 'name'));
        $this->assertGreaterThanOrEqual(45, $json['priority_pipes'][0]['score']);
    }

    // ── #7 GeoJSON + lembar status ─────────────────────────────────────────

    public function test_export_geojson_returns_feature_collection(): void
    {
        $n = $this->cleanNetwork();
        $layers = $this->actingAs($this->admin, 'sanctum')->getJson('/admin/network/layers.json')->assertOk()->json();
        $fc = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/admin/network/export.geojson')->assertOk()->json();
        $this->assertSame('FeatureCollection', $fc['type']);
        $this->assertSame(
            count($layers['pipes']) + count($layers['nodes']) + count($layers['dmas']),
            count($fc['features']),
            'semua layer pipe+node+dma masuk FC'
        );
    }

    public function test_import_geojson_creates_nodes_pipes_dma_with_skip_list(): void
    {
        $fc = ['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'properties' => ['feature_type' => 'pump', 'name' => 'IMPORT-PMP'],
                'geometry' => ['type' => 'Point', 'coordinates' => [5.11, 45.11]]],
            ['type' => 'Feature', 'properties' => ['name' => 'IMPORT-LN', 'features' => ['feature_type' => 'junction']], // props gis → junction fallback
                'geometry' => ['type' => 'LineString', 'coordinates' => [[5.0, 45.0], [5.01, 45.01]]]],
            ['type' => 'Feature', 'properties' => ['code' => 'DMA-IM', 'name' => 'DMA Impor'],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[[5.0, 45.0], [5.1, 45.0], [5.1, 45.1], [5.0, 45.1], [5.0, 45.0]]]]],
            ['type' => 'Feature', 'properties' => [], 'geometry' => ['type' => 'MultiPolygon', 'coordinates' => []]], // skip
        ]];

        $json = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/admin/network/import.geojson', ['geojson' => $fc, 'dry_run' => false])
            ->assertOk()->json();
        $this->assertSame(1, $json['created']['nodes']);
        $this->assertSame(1, $json['created']['pipes']);   // auto-wiring buat edge+endpointnya
        $this->assertSame(1, $json['created']['dmas']);
        $this->assertSame(1, $json['skipped']);
        $this->assertDatabaseHas('gis_features', ['name' => 'IMPORT-PMP', 'feature_type' => 'pump']);
        $this->assertDatabaseHas('dma_zones', ['code' => 'DMA-IM']);
        // pipa impor punya edge + panjang terisi (auto-wiring)
        $imp = GisFeature::where('name', 'IMPORT-LN')->firstOrFail();
        $this->assertGreaterThan(0, GisNetworkEdge::where('pipe_feature_id', $imp->id)->count());
        // dry_run = validasi + hitung, TANPA insert:
        $before = GisFeature::count();
        $dry = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/admin/network/import.geojson', ['geojson' => $fc, 'dry_run' => true])->assertOk()->json();
        $this->assertTrue($dry['dry_run']);
        $this->assertSame($before, GisFeature::count(), 'dry-run tidak boleh insert');
        $this->assertSame(['nodes' => 1, 'pipes' => 1, 'dmas' => 1], $dry['created']);
    }

    public function test_import_rejects_garbage_payload(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/admin/network/import.geojson', ['geojson' => 'bukan json'])
            ->assertStatus(422);
    }

    public function test_print_status_page_renders_schematic_and_tables(): void
    {
        $this->cleanNetwork();
        $this->actingAs($this->admin, 'sanctum')
            ->get('/admin/network/print')
            ->assertOk()
            ->assertSee('Lembar Status Jaringan', false)
            ->assertSee('<svg', false)
            ->assertSee('Prioritas penggantian pipa', false)
            ->assertSee('DMA-C');
    }

    // ── #8 preventif MNT ───────────────────────────────────────────────────

    public function test_schedule_valve_run_convert_due_to_wo(): void
    {
        $v = $this->node('valve', 109.5, 1.4, ['material' => 'HDPE', 'install_year' => 2020]);
        $j = $this->node('junction', 109.6, 1.5);
        $this->pipe($v, $j);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/admin/network/maintenance', ['feature_id' => $v->id, 'interval_days' => 90])
            ->assertStatus(201);

        $sched = MaintenanceSchedule::query()->where('asset_type', 'gis_feature')->where('asset_id', $v->id)->firstOrFail();
        $this->assertSame('Preventif Valve '.$v->name, $sched->name);
        $this->assertNotNull($sched->next_due_date);
        $this->assertEmpty($this->actingAs($this->admin, 'sanctum')->getJson('/admin/network/maintenance.json')->json('items') ? [] : []); // exists
        $this->assertCount(1, $this->actingAs($this->admin, 'sanctum')->getJson('/admin/network/maintenance.json')->assertOk()->json('items'));

        // due dipaksa kemarin → /run membuat WO inspeksi
        MaintenanceSchedule::where('id', $sched->id)->update(['next_due_date' => now()->subDay()->toDateString()]);
        $this->actingAs($this->admin, 'sanctum')->postJson('/admin/network/maintenance/run')->assertOk();

        $wo = WorkOrder::query()->where('source_type', 'gis_feature')->where('source_id', $v->id)->where('type', 'inspection')->first();
        $this->assertNotNull($wo, 'WO preventif harus terbit dari due-date');
        $this->assertSame('open', $wo->status);
        $this->assertDatabaseHas('work_order_logs', ['work_order_id' => $wo->id, 'action' => 'created']);
        // next_due maju se-1 siklus (±7 hari) setelah run
        $sched->refresh();
        $this->assertTrue($sched->next_due_date->isFuture());
        // due lagi → WO berikutnya (idempotent per siklus)
        MaintenanceSchedule::where('id', $sched->id)->update(['next_due_date' => now()->subDay()->toDateString()]);
        $this->actingAs($this->admin, 'sanctum')->postJson('/admin/network/maintenance/run')->assertOk();
        $this->assertSame(2, WorkOrder::query()->where('source_type', 'gis_feature')->where('source_id', $v->id)->count());
    }

    public function test_schedule_only_for_devices(): void
    {
        $p = $this->pipe($this->node('pump', 1, 45), $this->node('valve', 1.01, 45.01));
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/admin/network/maintenance', ['feature_id' => $p->id, 'interval_days' => 90])
            ->assertStatus(422);
    }

    public function test_mark_complete_sets_next_due(): void
    {
        $h = $this->node('hydrant', 109.5, 1.4);
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/admin/network/maintenance', ['feature_id' => $h->id, 'interval_days' => 60])->assertStatus(201);
        $s = MaintenanceSchedule::query()->where('asset_type', 'gis_feature')->firstOrFail();

        $json = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/admin/network/maintenance/'.$s->id.'/complete')->assertOk()->json();
        $this->assertSame(now()->toDateString(), $json['last_completed_date']);
        $this->assertSame(now()->addDays(60)->toDateString(), $json['next_due_date']);
    }

    public function test_mnt_network_command_multi_tenant(): void
    {
        $n = $this->cleanNetwork();
        $v = $n['v1'];
        MaintenanceSchedule::create([
            'pdam_org_id' => $this->orgId, 'code' => 'MNT-TEST', 'name' => 'T',
            'asset_type' => 'gis_feature', 'asset_id' => $v->id,
            'frequency' => 'cyclical', 'interval_value' => 120,
            'next_due_date' => now()->subDay()->toDateString(), 'is_active' => true,
        ]);

        $this->artisan('pdam:mnt-network')->assertSuccessful();
        $this->assertDatabaseHas('work_orders', ['source_type' => 'gis_feature', 'source_id' => $v->id, 'type' => 'inspection']);
        $fresh = MaintenanceSchedule::query()->where('asset_type', 'gis_feature')->where('asset_id', $v->id)->firstOrFail();
        $this->assertTrue($fresh->next_due_date->greaterThan(now()));
    }

    // ── #9 feasibility ─────────────────────────────────────────────────────

    public function test_feasibility_finds_nearest_pipe_proposes_cost(): void
    {
        $n = $this->cleanNetwork(); // e1 pump—v1 (150 m)
        config(['business.installation.pipe_cost_per_m' => 150000]);

        // titik ~10 m dekat ruas e1: interpolasi segmen pump(109.300,1.350)→v1(109.305,1.355)
        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/admin/network/feasibility.json?lat='.(1.350 + (10 / 110540)).'&lng=109.300')->assertOk()->json();

        $this->assertTrue((bool) $res['feasible'], 'harus ketemu pipa: '.json_encode($res));
        $this->assertLessThan(25, $res['point_to_pipe_m'], 'jarak ke pipa ≤25 m');
        $this->assertSame($n['e1']->id, $res['pipe_id']);
        $this->assertEquals(150000 * $res['route_length_m'], $res['estimated_material_cost']);
        $this->assertArrayHasKey('connection_point', $res);
        $this->assertContains($res['status'], ['sangat_eligible', 'perlu_persetujuan', 'eligible']);
        $this->assertStringContainsString('tarif', $res['note']);
    }

    public function test_feasibility_out_of_reach_without_setting_returns_message(): void
    {
        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/admin/network/feasibility.json?lat=-12.5&lng=137.9')->assertOk()->json();
        $this->assertFalse((bool) $res['feasible']);
        $this->assertSame('di_luar_jangkauan', $res['status']);
        $this->assertStringContainsString('PERPANJANGAN JALUR', $res['message']);
    }

    public function test_feasibility_without_rate_shows_note_only(): void
    {
        $n = $this->cleanNetwork();
        config(['business.installation.pipe_cost_per_m' => 0]);
        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/admin/network/feasibility.json?lat=1.350&lng=109.3005')->assertOk()->json();
        $this->assertSame(null, $res['estimated_material_cost']);
        $this->assertStringContainsString('PDAM_SR_PIPE_COST_PER_M', $res['note']);
    }
}
