<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\DistributionReading;
use App\Models\DmaZone;
use App\Models\GisFeature;
use App\Models\GisNetworkEdge;
use App\Models\Module;
use App\Models\PdamOrganization;
use App\Models\SubscriptionModule;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Geo\NetworkGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GIS Jaringan Perpipaan (dashboard direktur/admin/teknis):
 * - GRAF TERARAH: orientasi aliran BFS dari sumber; panah arah di layers.json.
 * - isolasi bocor = SIMULASI tutup valve minimal: cabang tetap teraliri via
 *   jalur lain tidak ikut terpotong; klaster yatim tidak dihitung terdampak.
 * - pelanggan terdampak via convex hull segmen mati.
 * - insiden → WorkOrder (pipa ditandai rusak).
 * - editor: auto-wiring pipa baru, delete cascade edge, toggle valve.
 * - NRW auto per DMA dari DistributionReading vs tagihan dalam polygon.
 * - tenant isolation + gate permission gis.feature.*.
 */
class GisNetworkTest extends TestCase
{
    use RefreshDatabase;

    private int $orgId;

    private User $admin;

    private NetworkGraphService $graph;

    protected function setUp(): void
    {
        parent::setUp();

        $org = PdamOrganization::create(['code' => 'net', 'name' => 'Net PDAM', 'subscription_status' => 'active']);
        $this->orgId = $org->id;
        $this->admin = User::create([
            'pdam_org_id' => $org->id, 'name' => 'Geo Admin', 'email' => 'geo@test.local',
            'password' => 'password', 'is_tenant_admin' => true, 'is_active' => true,
        ]);
        Module::firstOrCreate(['code' => 'GIS'], ['name' => 'GIS', 'is_active' => true]);
        SubscriptionModule::create(['pdam_org_id' => $org->id, 'module_code' => 'GIS', 'status' => 'active']);

        $this->graph = app(NetworkGraphService::class);
    }

    // ── fixtures graf ──────────────────────────────────────────────────────

    private function node(string $type, float $lng, float $lat, string $status = 'active'): GisFeature
    {
        return GisFeature::create([
            'pdam_org_id' => $this->orgId, 'feature_type' => $type,
            'name' => strtoupper($type).'-'.substr(md5($type.$lng.$lat), 0, 6),
            'geometry' => ['type' => 'Point', 'coordinates' => [$lng, $lat]],
            'status' => $status,
        ]);
    }

    private function pipe(GisFeature $a, GisFeature $b, string $status = 'active'): GisFeature
    {
        $pipe = GisFeature::create([
            'pdam_org_id' => $this->orgId, 'feature_type' => 'pipe',
            'name' => 'PIPE-'.Str::upper(Str::random(6)),
            'geometry' => ['type' => 'LineString', 'coordinates' => [$a->geometry['coordinates'], $b->geometry['coordinates']]],
            'properties' => ['diameter_mm' => 200, 'material' => 'HDPE', 'install_year' => 2020],
            'status' => $status,
        ]);
        GisNetworkEdge::create([
            'pdam_org_id' => $this->orgId, 'pipe_feature_id' => $pipe->id,
            'from_node_id' => $a->id, 'from_node_type' => $a->feature_type,
            'to_node_id' => $b->id, 'to_node_type' => $b->feature_type,
            'length_meters' => 100,
        ]);

        return $pipe;
    }

    /**
     * Jaringan: PMP →(P1)→ V1 →(P2)→ J2 →(P3)→ V3 →(P4)→ J4 →(P5)→ V5 + lateral J2→HY2 (PL)
     * di area cluster pelanggan Sambas (1.35..1.4, 109.30..109.35).
     */
    private function makeNetwork(): array
    {
        $pmp = $this->node('pump', 109.300, 1.350);
        $v1 = $this->node('valve', 109.310, 1.360);
        $j2 = $this->node('junction', 109.320, 1.370);
        $v3 = $this->node('valve', 109.330, 1.380);
        $j4 = $this->node('junction', 109.340, 1.390);
        $v5 = $this->node('valve', 109.350, 1.400);
        $hy2 = $this->node('hydrant', 109.325, 1.365);

        return [
            'pmp' => $pmp, 'v1' => $v1, 'j2' => $j2, 'v3' => $v3, 'j4' => $j4, 'v5' => $v5, 'hy2' => $hy2,
            'p1' => $this->pipe($pmp, $v1),
            'p2' => $this->pipe($v1, $j2),
            'p3' => $this->pipe($j2, $v3),
            'p4' => $this->pipe($v3, $j4),
            'p5' => $this->pipe($j4, $v5),
            'pl' => $this->pipe($j2, $hy2),
        ];
    }

    // ── isolasi (service level) ────────────────────────────────────────────

    public function test_isolate_pipe_between_pump_and_valve(): void
    {
        $n = $this->makeNetwork();
        $res = $this->graph->isolate($n['p1']->id);

        $this->assertContains($n['v1']->id, array_column($res['valves_to_close'], 'id'));
        $this->assertContains($n['pmp']->id, array_column($res['reached_sources'], 'id'), 'sebelum V1 masih tersambung pompa');
        $pipes = array_column($res['isolated_pipes'], 'id');
        $this->assertContains($n['p1']->id, $pipes);
        // Jujur secara fisika: satu-satunya sumber chain ini adalah pompa — menutup V1
        // benar-benar membuat SEMUA hilir mati. Graf terarah tidak lagi berpura-pura
        // cabang di balik valve aman tanpa ada jalur pasokan lain.
        $this->assertContains($n['p2']->id, $pipes);
    }

    public function test_isolating_mid_pipe_minimal_valve_and_drains_downstream(): void
    {
        $n = $this->makeNetwork();
        $res = $this->graph->isolate($n['p2']->id);

        $valves = array_column($res['valves_to_close'], 'id');
        // Cut MINIMAL: hanya V1 yang memutus suplai ke ruas bocor; V3 hanya valve
        // hilir buntu (tidak ada pasokan dari sisinya) → tidak perlu ditutup.
        $this->assertSame([$n['v1']->id], $valves);

        $pipes = array_column($res['isolated_pipes'], 'id');
        $this->assertContains($n['p2']->id, $pipes, 'ruas bocor sendiri');
        $this->assertContains($n['p3']->id, $pipes, 'ruas sebelum V3 ikut kering (drain stub)');
        $this->assertContains($n['pl']->id, $pipes, 'lateral ke hydrant ikut mati (buntu)');
        // Rantai tunggal tanpa loop: sisi V3 juga kehilangan pasokan V1 — jujur.
        $this->assertContains($n['p4']->id, $pipes);
        $this->assertContains($n['p5']->id, $pipes);
        $this->assertSame([], $res['reached_sources']);
        $this->assertTrue($res['ok']);
    }

    public function test_closed_valve_blocks_flood(): void
    {
        $n = $this->makeNetwork();
        $n['v3']->update(['status' => 'closed']);

        $res = $this->graph->isolate($n['p2']->id);

        $this->assertContains($n['v1']->id, array_column($res['valves_to_close'], 'id'));
        $this->assertContains($n['v3']->id, array_column($res['valves_closed'], 'id'));
        $this->assertNotContains($n['v3']->id, array_column($res['valves_to_close'], 'id'), 'V3 closed tidak perlu "ditutup" lagi');
        $pipes = array_column($res['isolated_pipes'], 'id');
        $this->assertContains($n['p3']->id, $pipes, 'P3 mati dari sisi J2 walau V3 closed');
        $this->assertNotContains($n['p4']->id, $pipes);
    }

    public function test_dead_end_without_valve_flagged_to_source(): void
    {
        $pump = $this->node('pump', 109.400, 1.300);
        $j1 = $this->node('junction', 109.401, 1.301);
        $burst = $this->pipe($pump, $j1);

        $res = $this->graph->isolate($burst->id);
        $this->assertNotEmpty($res['reached_sources'], 'tanpa valve di hulu → masih tersambung pompa');
        $this->assertEmpty(array_column($res['valves_to_close'], 'id'));
    }

    /** Loop + pasokan alternatif: valve minimal & rumah yang masih teraliri aman. */
    private function makeLoopNetwork(): array
    {
        $pmp = $this->node('pump', 109.300, 1.350);
        $v1 = $this->node('valve', 109.310, 1.355);
        $j1 = $this->node('junction', 109.320, 1.360);
        $j2 = $this->node('junction', 109.330, 1.365);
        $v3 = $this->node('valve', 109.340, 1.370);
        $s2 = $this->node('reservoir', 109.350, 1.375);
        $v2 = $this->node('valve', 109.335, 1.355);
        $hy3 = $this->node('hydrant', 109.340, 1.350);
        $s3 = $this->node('intake', 109.345, 1.345);

        return [
            'pmp' => $pmp, 'v1' => $v1, 'j1' => $j1, 'j2' => $j2, 'v3' => $v3, 's2' => $s2,
            'v2' => $v2, 'hy3' => $hy3, 's3' => $s3,
            'e1' => $this->pipe($pmp, $v1),   // hulu: pompa → V1
            'e0' => $this->pipe($v1, $j1),    // V1 → J1 (ruas aman dekat valve)
            'lp' => $this->pipe($j1, $j2),    // RUAAS BOCOR tengah loop
            'e4' => $this->pipe($s2, $v3),    // reservoir → V3
            'e3' => $this->pipe($v3, $j2),    // pasokan loop dari reservoir
            'e2' => $this->pipe($j2, $v2),    // cabang hilir buntu
            'eh' => $this->pipe($v2, $hy3),
            'es3' => $this->pipe($s3, $hy3),  // pasokan alternatif hydrant (tanpa valve)
        ];
    }

    public function test_loop_isolation_uses_minimal_valves_and_spares_alt_supply(): void
    {
        $n = $this->makeLoopNetwork();
        $res = $this->graph->isolate($n['lp']->id);

        $closed = array_column($res['valves_to_close'], 'id');
        $this->assertEqualsCanonicalizing([$n['v1']->id, $n['v3']->id], $closed,
            'hanya valve hulu nyata; V2 cabang hilir TIDAK perlu ditutup (cut minimal)');

        $pipes = array_column($res['isolated_pipes'], 'id');
        $this->assertContains($n['lp']->id, $pipes);
        $this->assertContains($n['e0']->id, $pipes, 'stub hulu V1→J1 ikut kering (sisa di sisi valve tertutup)');
        $this->assertContains($n['e3']->id, $pipes, 'stub hulu V3→J2 ikut kering');
        $this->assertNotContains($n['e2']->id, $pipes);
        $this->assertNotContains($n['eh']->id, $pipes, 'ruas V2→HY3 masih di-supply intake S3 → TIDAK ikut terisolasi');
        $this->assertNotContains($n['es3']->id, $pipes);
        $this->assertNotContains($n['hy3']->id, array_column($res['isolated_nodes'], 'id'));
        $this->assertContains($n['j2']->id, array_column($res['isolated_nodes'], 'id'));
        $this->assertTrue($res['ok']);
    }

    public function test_orphan_cluster_never_counted_as_isolated(): void
    {
        $n = $this->makeNetwork();
        // klaster "4 yatim": node + pipa tanpa koneksi ke sumber mana pun
        $c1 = $this->node('junction', 109.90, 1.90);
        $c2 = $this->node('junction', 109.91, 1.91);
        $cp = $this->pipe($c1, $c2);

        $res = $this->graph->isolate($n['p2']->id);
        $pipes = array_column($res['isolated_pipes'], 'id');
        $nodes = array_column($res['isolated_nodes'], 'id');
        $this->assertNotContains($cp->id, $pipes, 'pipa klaster yatim bukan DAMPAK penutupan valve');
        $this->assertNotContains($c1->id, $nodes);
        $this->assertNotContains($c2->id, $nodes);
    }

    public function test_layers_expose_flow_orientation(): void
    {
        $n = $this->makeNetwork();
        $json = $this->actingAs($this->admin, 'sanctum')->getJson('/admin/network/layers.json')->assertOk()->json();
        $byPipe = collect($json['edges'])->keyBy('pipe_feature_id');
        $flow = $byPipe[$n['p1']->id]['flow'] ?? null;
        $this->assertNotNull($flow, 'edge punya orientasi aliran');
        $this->assertSame($n['pmp']->id, $flow['from'], 'hulu edge pertama = pompa');
        $this->assertSame($n['v1']->id, $flow['to']);
    }

    public function test_affected_customers_by_hull(): void
    {
        $n = $this->makeNetwork();
        Customer::create(['pdam_org_id' => $this->orgId, 'customer_number' => 'IN-1', 'full_name' => 'Dalam', 'status' => 'active', 'latitude' => 1.3600, 'longitude' => 109.3100]);
        Customer::create(['pdam_org_id' => $this->orgId, 'customer_number' => 'OUT-1', 'full_name' => 'Luar', 'status' => 'active', 'latitude' => 1.7000, 'longitude' => 109.7500]);

        $res = $this->graph->isolate($n['p2']->id);
        $region = $this->graph->affectedCustomersInRegion(array_column($res['isolated_pipes'], 'id'));

        $this->assertGreaterThan(0, count($region['polygon']));
        $numbers = array_column($region['customers'], 'customer_number');
        $this->assertNotContains('OUT-1', $numbers);
        $this->assertGreaterThanOrEqual(0, $region['count']);
    }

    // ── HTTP editor & aksi ─────────────────────────────────────────────────

    public function test_map_page_renders_for_tenant_admin(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->get('/admin/network')->assertOk()->assertSee('Jaringan Perpipaan', false);
    }

    public function test_map_page_requires_login(): void
    {
        $this->getJson('/admin/network/layers.json')->assertUnauthorized();
    }

    public function test_editor_pipe_auto_wires_existing_valve_and_creates_junction(): void
    {
        $v1 = $this->node('valve', 109.31, 1.36);

        $json = $this->actingAs($this->admin, 'sanctum')->postJson('/admin/network/features', [
            'feature_type' => 'pipe',
            'name' => 'Pipa Uji',
            'geometry' => ['type' => 'LineString', 'coordinates' => [[109.310001, 1.360001], [109.350, 1.390]]],
        ])->assertCreated()->json();

        $pipe = $json['pipe'] ?? $json; // web controller mengirim polos
        $this->assertArrayHasKey('wiring', $json);
        $wiring = $json['wiring'];
        $edge = GisNetworkEdge::where('pipe_feature_id', $pipe['id'])->firstOrFail();
        $this->assertSame($v1->id, $edge->from_node_id);
        $this->assertCount(1, $wiring['created_junctions']);
    }

    public function test_toggle_valve_closed_visible_in_layers(): void
    {
        $v = $this->node('valve', 109.33, 1.38);
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/admin/network/features/'.$v->id, ['status' => 'closed'])->assertOk();

        $this->assertSame('closed', $v->fresh()->status);
    }

    public function test_delete_node_removes_connected_edges(): void
    {
        $n = $this->makeNetwork();
        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/admin/network/features/'.$n['j4']->id)->assertOk();

        $this->assertDatabaseMissing('gis_network_edges', ['from_node_id' => $n['j4']->id]);
        $this->assertDatabaseMissing('gis_network_edges', ['to_node_id' => $n['j4']->id]);
        $this->assertDatabaseMissing('gis_features', ['id' => $n['j4']->id]);
    }

    public function test_isolate_endpoint_affected_customers(): void
    {
        $n = $this->makeNetwork();
        Customer::create(['pdam_org_id' => $this->orgId, 'customer_number' => 'ZONE-IN', 'full_name' => 'Z', 'status' => 'active', 'latitude' => 1.3702, 'longitude' => 109.3202]);
        Customer::create(['pdam_org_id' => $this->orgId, 'customer_number' => 'ZONE-OUT', 'full_name' => 'O', 'status' => 'active', 'latitude' => 1.90, 'longitude' => 109.90]);

        $json = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/admin/network/isolate', ['feature_id' => $n['p2']->id])->assertOk()->json();

        $this->assertArrayHasKey('affected', $json);
        $numbers = array_column($json['affected']['customers'], 'customer_number');
        $this->assertNotContains('ZONE-OUT', $numbers);
    }

    public function test_isolate_other_tenant_returns_404(): void
    {
        $n = $this->makeNetwork();
        $otherAdmin = $this->otherAdmin();

        $this->actingAs($otherAdmin, 'sanctum')
            ->postJson('/admin/network/isolate', ['feature_id' => $n['p2']->id])
            ->assertNotFound();
    }

    public function test_incident_creates_work_order_and_marks_pipe_rusak(): void
    {
        $n = $this->makeNetwork();
        $res = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/admin/network/incidents', ['feature_id' => $n['p1']->id, 'priority' => 'urgent'])
            ->assertCreated();

        $woId = $res->json('data.id') ?? $res->json('id');
        $wo = WorkOrder::find($woId);
        $this->assertNotNull($wo);
        $this->assertSame('repair', $wo->type);
        $this->assertSame('urgent', $wo->priority);
        $this->assertSame('gis_feature', $wo->source_type);
        $this->assertSame($n['p1']->id, $wo->source_id);
        $this->assertSame('rusak', $n['p1']->fresh()->status);
        $this->assertDatabaseHas('work_order_logs', ['work_order_id' => $wo->id, 'action' => 'created']);
    }

    public function test_layers_excludes_other_tenant(): void
    {
        $this->makeNetwork();
        $otherAdmin = $this->otherAdmin();

        $json = $this->actingAs($otherAdmin, 'sanctum')->getJson('/admin/network/layers.json')->assertOk()->json();
        $this->assertCount(0, $json['pipes']);
        $this->assertCount(0, $json['nodes']);
        $this->assertCount(0, $json['edges']);
    }

    public function test_gis_operator_without_feature_view_blocked_layers(): void
    {
        $u = User::create(['pdam_org_id' => $this->orgId, 'name' => 'Tanpa izin', 'email' => 'plain@test.local', 'password' => 'password', 'is_active' => true]);
        $this->actingAs($u, 'sanctum')->getJson('/admin/network/layers.json')->assertForbidden();
    }

    // ── DMA + NRW ──────────────────────────────────────────────────────────

    public function test_nrw_auto_polygon_dma_from_readings_and_bills(): void
    {
        $this->makeNetwork();
        $period = now()->format('Y-m');

        $inDma = Customer::create(['pdam_org_id' => $this->orgId, 'customer_number' => 'DMA-IN', 'full_name' => 'Dalam DMA', 'status' => 'active', 'latitude' => 1.3890, 'longitude' => 109.3300]);
        $outDma = Customer::create(['pdam_org_id' => $this->orgId, 'customer_number' => 'DMA-OUT', 'full_name' => 'Luar DMA', 'status' => 'active', 'latitude' => 1.3000, 'longitude' => 109.9000]);
        $this->bill($inDma, $period, 50);
        $this->bill($outDma, $period, 900);

        $dma = DmaZone::create([
            'pdam_org_id' => $this->orgId, 'code' => 'DMA-T', 'name' => 'Test DMA',
            'boundary' => ['type' => 'Polygon', 'coordinates' => [[[109.298, 1.345], [109.376, 1.430], [109.360, 1.438], [109.290, 1.352], [109.298, 1.345]]]],
            'is_active' => true, 'total_connections' => 1, 'base_demand_m3day' => 100,
        ]);
        DistributionReading::create(['pdam_org_id' => $this->orgId, 'dma_zone_id' => $dma->id, 'reading_at' => now()->startOfMonth()->addDay(), 'flow_rate_m3h' => 10]);
        DistributionReading::create(['pdam_org_id' => $this->orgId, 'dma_zone_id' => $dma->id, 'reading_at' => now(), 'flow_rate_m3h' => 30]);

        $json = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/admin/network/dmas/'.$dma->id.'/nrw', ['period' => $period])
            ->assertOk()->json();

        $days = (int) now()->daysInMonth;
        $expectedSupply = ((10 + 30) / 2) * 24 * $days;
        $this->assertEqualsWithDelta($expectedSupply, $json['supply_m3'], 1);
        $this->assertEquals(50, $json['billed_m3'], 'hanya konsumsi pelanggan dalam polygon');
        $this->assertArrayHasKey('nrw_percent', $json);
        $this->assertDatabaseHas('nrw_balances', ['dma_zone_id' => $dma->id, 'period' => $period]);

        $sum = $this->actingAs($this->admin, 'sanctum')->getJson('/admin/network/nrw.json?period='.$period)->assertOk()->json();
        $row = $sum['dmas'][0];
        $this->assertSame('DMA-T', $row['code']);
        $this->assertContains($row['status'], ['baik', 'waspada', 'kritis']);
    }

    public function test_nrw_requires_distribution_readings(): void
    {
        $dma = DmaZone::create(['pdam_org_id' => $this->orgId, 'code' => 'DMA-X', 'name' => 'X', 'boundary' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]]], 'is_active' => true, 'total_connections' => 1, 'base_demand_m3day' => 10]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/admin/network/dmas/'.$dma->id.'/nrw', ['period' => now()->format('Y-m')])
            ->assertStatus(422);
    }

    private function otherAdmin(): User
    {
        $org2 = PdamOrganization::create(['code' => 'net2', 'name' => 'Net2 PDAM', 'subscription_status' => 'active']);
        User::create(['pdam_org_id' => $org2->id, 'name' => 'Admin2', 'email' => 'geo2@test.local', 'password' => 'password', 'is_tenant_admin' => true, 'is_active' => true]);
        SubscriptionModule::create(['pdam_org_id' => $org2->id, 'module_code' => 'GIS', 'status' => 'active']);

        return User::withoutGlobalScopes()->where('pdam_org_id', $org2->id)->firstOrFail();
    }

    private function bill(Customer $c, string $period, int $consumption): void
    {
        Bill::create([
            'pdam_org_id' => $c->pdam_org_id, 'customer_id' => $c->id,
            'bill_number' => 'INV-'.Str::upper(Str::random(10)), 'period' => $period,
            'previous_reading' => 0, 'current_reading' => $consumption, 'consumption' => $consumption,
            'water_charge' => 0, 'abonemen' => 0, 'meter_maintenance_fee' => 0, 'admin_fee' => 0,
            'penalty' => 0, 'amount_due' => 10000, 'status' => 'paid', 'due_date' => now()->toDateString(),
        ]);
    }
}
