<?php

namespace Tests\Feature;

use App\Models\GisFeature;
use App\Models\GisNetworkEdge;
use App\Models\Module;
use App\Models\PdamOrganization;
use App\Models\Role;
use App\Models\SubscriptionModule;
use App\Models\TechnicianLocation;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Geo\FieldLocationService;
use App\Services\Geo\NetworkGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Petugas LIVE di peta GIS Jaringan — integrasi dua arah dengan mobile:
 * 1. Lapor lokasi: POST /api/v1/field/location (GPS asli dari GpsHelper).
 * 2. Piggyback: coordinate laporan survey → tercatat juga sbg lokasi petugas.
 * 3. GET /admin/network/technicians.json → titik per role petugas + online.
 * 4. POST /admin/network/dispatch → WO ter-assign ke officer terdekat + rute
 *    OSRM (garis lurus sbg fallback bila OSRM mati).
 * 5. Health map: service nearest/ranked benar.
 */
class FieldOfficerMapTest extends TestCase
{
    use RefreshDatabase;

    private int $orgId;
    private User $admin;
    private User $tech1;
    private User $tech2;
    private FieldLocationService $field;

    protected function setUp(): void
    {
        parent::setUp();

        $org = PdamOrganization::create(['code' => 'officer-test', 'name' => 'PT Officer PDAM', 'subscription_status' => 'active']);
        $this->orgId = $org->id;
        $this->admin = User::create([
            'pdam_org_id' => $org->id, 'name' => 'GIS Admin', 'email' => 'officer@gis.local',
            'password' => 'password', 'is_tenant_admin' => true, 'is_active' => true,
        ]);
        Module::firstOrCreate(['code' => 'GIS'], ['name' => 'GIS', 'is_active' => true]);
        SubscriptionModule::create(['pdam_org_id' => $org->id, 'module_code' => 'GIS', 'status' => 'active']);

        // Role field (per-tenant seperti provisioning asli; service juga
        // menahan template global org-null).
        $role = Role::create(['pdam_org_id' => $org->id, 'code' => 'field_technician', 'name' => 'Field Technician']);

        $this->tech1 = $this->staff('Teknisi A', 't1@test.local');
        $this->tech1->roles()->attach($role);
        $this->tech2 = $this->staff('Teknisi B', 't2@test.local');
        $this->tech2->roles()->attach($role);

        // Role lain (bukan petugas lapangan) — tidak boleh muncul di layer.
        $clerkRole = Role::create(['pdam_org_id' => $org->id, 'code' => 'billing_clerk', 'name' => 'Billing Clerk']);
        $clerk = $this->staff('Admin Tagihan', 'clerk@test.local');
        $clerk->roles()->attach($clerkRole);

        $this->field = app(FieldLocationService::class);
    }

    private function staff(string $name, string $email): User
    {
        return User::create(['pdam_org_id' => $this->orgId, 'name' => $name, 'email' => $email, 'password' => 'password', 'is_active' => true]);
    }

    // ── lapor lokasi ────────────────────────────────────────────────────

    public function test_field_location_endpoint_records_officer(): void
    {
        $this->actingAs($this->tech1, 'sanctum')
            ->postJson('/api/v1/field/location', ['latitude' => -0.7, 'longitude' => 109.2, 'accuracy' => 7.5])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $this->tech1->id);

        $loc = TechnicianLocation::where('user_id', $this->tech1->id)->firstOrFail();
        $this->assertEquals(-0.7, (float) $loc->latitude);
        $this->assertEquals(7.5, (float) $loc->accuracy);

        // upsert, bukan duplikat
        $this->actingAs($this->tech1, 'sanctum')
            ->postJson('/api/v1/field/location', ['latitude' => -0.71, 'longitude' => 109.21])
            ->assertCreated();
        $this->assertSame(1, TechnicianLocation::where('user_id', $this->tech1->id)->count());
    }

    public function test_api_field_location_requires_latlng(): void
    {
        $this->actingAs($this->tech1, 'sanctum')
            ->postJson('/api/v1/field/location', ['latitude' => 999])
            ->assertStatus(422);
    }

    public function test_piggyback_location_from_submit(): void
    {
        $this->actingAs($this->tech1, 'sanctum');
        $this->field->report($this->tech1, -0.72, 109.22, 4.0);
        $this->assertDatabaseHas('technician_locations', ['user_id' => $this->tech1->id]);
    }

    // ── peta live ───────────────────────────────────────────────────────

    public function test_technicians_json_lists_only_field_roles(): void
    {
        $this->field->report($this->tech1, -0.7, 109.2, 5);
        $this->field->report($this->tech2, -0.9, 109.5, 5);

        $json = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/admin/network/technicians.json')->assertOk()->json();

        $this->assertSame(30, $json['stale_minutes']);
        $names = array_column($json['items'], 'name');
        sort($names);
        $this->assertSame(['Teknisi A', 'Teknisi B'], $names); // clerk & admin tersaring
        $this->assertTrue($json['items'][0]['online']);
    }

    public function test_offline_flag_when_stale(): void
    {
        $this->field->report($this->tech1, -0.7, 109.2);
        TechnicianLocation::where('user_id', $this->tech1->id)->update(['updated_at' => now()->subMinutes(90)]);

        $json = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/admin/network/technicians.json')->assertOk()->json();

        $this->assertCount(1, $json['items']);
        $this->assertFalse($json['items'][0]['online']);
    }

    // ── dispatch ────────────────────────────────────────────────────────

    public function test_dispatch_assigns_nearest_officer_with_route(): void
    {
        $pipe = $this->burstAt(109.300, 1.350); // node pump+valve+pipa di sana
        $this->field->report($this->tech1, 1.35500, 109.30000); // ~550 m
        $this->field->report($this->tech2, 1.40000, 109.30000); // ~5.5 km

        Http::fake([
            '*/route/v1/*' => Http::response([
                'code' => 'Ok',
                'routes' => [[
                    'geometry' => ['type' => 'LineString', 'coordinates' => [[109.30, 1.355], [109.30, 1.350]]],
                    'distance' => 800, 'duration' => 180,
                    'legs' => [['distance' => 800, 'duration' => 180]],
                ]],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $json = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/admin/network/dispatch', ['feature_id' => $pipe->id, 'priority' => 'urgent'])
            ->assertCreated()->json();

        $this->assertSame($this->tech1->id, $json['officer']['user_id'], 'petugas terdekat yang di-assign');
        $wo = WorkOrder::find($json['work_order']['id']);
        $this->assertNotNull($wo);
        $this->assertSame($this->tech1->id, $wo->assigned_to);
        $this->assertSame('gis_feature', $wo->source_type);
        $this->assertSame('assigned', $wo->status);
        $this->assertDatabaseHas('work_order_logs', ['work_order_id' => $wo->id, 'action' => 'dispatched']);
        $this->assertSame(800, (int) $json['route']['distance_m']);
        $this->assertLessThan(1200, $json['officer']['distance_m'], 'jaris lurus officer ke titik insiden (meter)');
        // notif in-app untuk petugas
        $this->assertDatabaseHas('app_notifications', ['user_id' => $this->tech1->id]);
    }

    public function test_dispatch_no_online_officer_returns_candidates(): void
    {
        $pipe = $this->burstAt(109.300, 1.350);
        $this->field->report($this->tech1, 1.36, 109.31);
        TechnicianLocation::where('user_id', $this->tech1->id)->update(['updated_at' => now()->subHours(6)]);

        $res = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/admin/network/dispatch', ['feature_id' => $pipe->id])
            ->assertStatus(422);
        $res->assertJsonPath('error', 'Tidak ada petugas online dalam 24 jam terakhir. Lapor lokasi via mobile dahulu.');
        $this->assertCount(1, $res->json('candidates'));
        $this->assertFalse($res->json('candidates.0.online'));
    }

    public function test_nearest_officer_service_picks_closest(): void
    {
        $this->field->report($this->tech1, 1.50, 109.50);
        $this->field->report($this->tech2, 1.40020, 109.40000);

        $near = $this->field->nearestOfficer($this->orgId, 1.40, 109.40);
        $this->assertSame($this->tech2->id, $near['user_id']);
        $this->assertTrue($near['distance_m'] < 50);

        $ranked = $this->field->rankedOfficers($this->orgId, 1.40, 109.40);
        $this->assertSame([$this->tech2->id, $this->tech1->id], array_column($ranked->all(), 'user_id'));
    }

    public function test_tenant_isolation_officers_never_cross_org(): void
    {
        $other = PdamOrganization::create(['code' => 'off-other', 'name' => 'Other PDAM', 'subscription_status' => 'active']);
        $otherAdmin = User::create(['pdam_org_id' => $other->id, 'name' => 'Admin Other', 'email' => 'oa@test.local', 'password' => 'password', 'is_tenant_admin' => true, 'is_active' => true]);
        SubscriptionModule::create(['pdam_org_id' => $other->id, 'module_code' => 'GIS', 'status' => 'active']);
        $this->field->report($this->tech1, 1.4, 109.4); // teknisi org ini

        $json = $this->actingAs($otherAdmin, 'sanctum')->getJson('/admin/network/technicians.json')->assertOk()->json();
        $this->assertSame([], array_column($json['items'], 'user_id'), 'tenant lain tidak melihat petugas org lain');
    }

    // ── fixture ─────────────────────────────────────────────────────────

    private function burstAt(float $lng, float $lat): GisFeature
    {
        $graph = app(NetworkGraphService::class);
        $start = GisFeature::create([
            'pdam_org_id' => $this->orgId, 'feature_type' => 'pump', 'name' => 'PMP-B',
            'geometry' => ['type' => 'Point', 'coordinates' => [$lng - 0.004, $lat + 0.004]], 'status' => 'active',
        ]);
        $end = GisFeature::create([
            'pdam_org_id' => $this->orgId, 'feature_type' => 'valve', 'name' => 'VLV-B',
            'geometry' => ['type' => 'Point', 'coordinates' => [$lng, $lat]], 'status' => 'active',
        ]);
        $pipe = GisFeature::create([
            'pdam_org_id' => $this->orgId, 'feature_type' => 'pipe', 'name' => 'PB-1',
            'geometry' => ['type' => 'LineString', 'coordinates' => [$start->geometry['coordinates'], [$lng, $lat]]],
            'properties' => ['diameter_mm' => 110, 'material' => 'Besi Tuang', 'install_year' => 1998,
                'length_meters' => $graph->lineLengthMeters([$start->geometry['coordinates'], [$lng, $lat]])],
            'status' => 'active',
        ]);
        GisNetworkEdge::create([
            'pdam_org_id' => $this->orgId, 'pipe_feature_id' => $pipe->id,
            'from_node_id' => $start->id, 'from_node_type' => 'pump',
            'to_node_id' => $end->id, 'to_node_type' => 'valve', 'length_meters' => 600,
        ]);

        return $pipe;
    }
}
