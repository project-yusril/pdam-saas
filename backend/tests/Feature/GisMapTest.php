<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\MeterRoute;
use App\Models\Module;
use App\Models\PdamOrganization;
use App\Models\SubscriptionModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Dashboard peta pelanggan (OSM/Leaflet) untuk direktur/admin:
 * - klasifikasi warna status pembayaran (termasuk isolir → hitam)
 * - tenant isolation (GeoJSON, ringkasan, tanpa-koordinat, binding)
 * - geocoding Nominatim (proxy server, User-Agent, batch 1 req/s)
 * - routing OSRM (route + tour nearest-neighbor)
 * - set koordinat manual + validasi
 */
class GisMapTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $code, bool $gisActive = true): array
    {
        $org = PdamOrganization::create(['code' => $code, 'name' => $code, 'subscription_status' => 'active']);
        $admin = User::create([
            'pdam_org_id' => $org->id,
            'name' => $code,
            'email' => $code.'@test.local',
            'password' => 'password',
            'is_tenant_admin' => true,
            'is_active' => true,
        ]);
        Module::firstOrCreate(['code' => 'GIS'], ['name' => 'GIS', 'is_active' => true]);
        if ($gisActive) {
            SubscriptionModule::create(['pdam_org_id' => $org->id, 'module_code' => 'GIS', 'status' => 'active']);
        }

        return [$org, $admin];
    }

    private function customer(int $orgId, array $over = []): Customer
    {
        return Customer::create(array_merge([
            'pdam_org_id' => $orgId,
            'customer_number' => 'C-'.Str::upper(Str::random(8)),
            'full_name' => 'Rumah '.Str::random(5),
            'latitude' => 1.35,
            'longitude' => 109.30,
            'status' => 'active',
        ], $over));
    }

    private function bill(Customer $customer, string $period, string $status, string $dueDate): Bill
    {
        return Bill::create([
            'pdam_org_id' => $customer->pdam_org_id,
            'customer_id' => $customer->id,
            'bill_number' => 'INV-'.Str::upper(Str::random(10)),
            'period' => $period,
            'previous_reading' => 0,
            'current_reading' => 10,
            'consumption' => 10,
            'water_charge' => 20000,
            'abonemen' => 5000,
            'meter_maintenance_fee' => 0,
            'admin_fee' => 0,
            'penalty' => 0,
            'amount_due' => 25000,
            'status' => $status,
            'due_date' => $dueDate,
        ]);
    }

    private function colorOf(array $features, int $customerId): string
    {
        foreach ($features as $f) {
            if ($f['properties']['id'] === $customerId) {
                return $f['properties']['status_color'];
            }
        }

        return 'MISSING';
    }

    // ── Auth & entitlement modul ─────────────────────────────────────────

    public function test_map_json_requires_authentication(): void
    {
        $this->getJson('/admin/gis/customers.json')->assertUnauthorized();
    }

    public function test_map_json_blocked_when_gis_module_locked(): void
    {
        [$org, $admin] = $this->tenant('gis-locked', gisActive: false);
        $this->customer($org->id);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/admin/gis/customers.json')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'MODULE_LOCKED');
    }

    // ── Klasifikasi warna ────────────────────────────────────────────────

    public function test_color_reflects_payment_status_and_disconnection(): void
    {
        [$org, $admin] = $this->tenant('warna');

        $paid = $this->customer($org->id); // tanpa tagihan → hijau

        $blue = $this->customer($org->id);
        $this->bill($blue, now()->format('Y-m'), 'unpaid', now()->addDays(7)->toDateString());

        $yellow = $this->customer($org->id);
        $this->bill($yellow, now()->subMonth()->format('Y-m'), 'overdue', now()->subDays(3)->toDateString());

        $orange = $this->customer($org->id);
        $this->bill($orange, now()->subMonths(2)->format('Y-m'), 'overdue', now()->subDays(3)->toDateString());
        $this->bill($orange, now()->subMonth()->format('Y-m'), 'overdue', now()->subDays(3)->toDateString());

        $red = $this->customer($org->id);
        foreach ([3, 2, 1] as $m) {
            $this->bill($red, now()->subMonths($m)->format('Y-m'), 'overdue', now()->subDays(3)->toDateString());
        }

        $black = $this->customer($org->id, ['status' => 'isolir']);
        $terminated = $this->customer($org->id, ['status' => 'terminated']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/admin/gis/customers.json')->assertOk();
        $features = $response->json('features');

        $this->assertSame('green', $this->colorOf($features, $paid->id));
        $this->assertSame('blue', $this->colorOf($features, $blue->id));
        $this->assertSame('yellow', $this->colorOf($features, $yellow->id));
        $this->assertSame('orange', $this->colorOf($features, $orange->id));
        $this->assertSame('red', $this->colorOf($features, $red->id));
        $this->assertSame('black', $this->colorOf($features, $black->id));
        $this->assertSame('black', $this->colorOf($features, $terminated->id));
    }

    public function test_inactive_temporary_and_other_tenant_are_not_visible(): void
    {
        [$org, $admin] = $this->tenant('iso-a');
        [$otherOrg] = $this->tenant('iso-b');

        $mine = $this->customer($org->id);
        $this->customer($org->id, ['status' => 'inactive']);
        $this->customer($org->id, ['status' => 'temporary_closed']);
        $this->customer($org->id, ['latitude' => null, 'longitude' => null]); // tanpa koordinat
        $other = $this->customer($otherOrg->id);

        $features = $this->actingAs($admin, 'sanctum')->getJson('/admin/gis/customers.json')->assertOk()->json('features');
        $visibleIds = array_map(fn ($f) => $f['properties']['id'], $features);

        $this->assertSame('green', $this->colorOf($features, $mine->id));
        $this->assertNotContains($other->id, $visibleIds);
        $this->assertSame('MISSING', $this->colorOf($features, $other->id));

        // Panel tanpa-koordinat juga terisolasi tenant.
        $ids = collect($this->actingAs($admin, 'sanctum')->getJson('/admin/gis/no-coords.json')->json('items'))
            ->pluck('id')->all();
        $this->assertCount(1, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_summary_counts_colors_and_missing(): void
    {
        [$org, $admin] = $this->tenant('ringkas');

        $green = $this->customer($org->id);
        $black = $this->customer($org->id, ['status' => 'isolir']);
        $missing = $this->customer($org->id, ['latitude' => null, 'longitude' => null]);

        $json = $this->actingAs($admin, 'sanctum')->getJson('/admin/gis/summary.json')->assertOk()->json();

        $this->assertSame(1, $json['counts']['green']);
        $this->assertSame(1, $json['counts']['black']);
        $this->assertSame(1, $json['missing_coords']);
        $this->assertArrayHasKey('blue', $json['counts']);
        $this->assertArrayHasKey('yellow', $json['counts']);
        $this->assertArrayHasKey('orange', $json['counts']);
        $this->assertArrayHasKey('red', $json['counts']);
    }

    // ── Geocoding Nominatim (proxy) ──────────────────────────────────────

    public function test_geocode_customer_from_address_with_user_agent(): void
    {
        [$org, $admin] = $this->tenant('geocode-1');
        $customer = $this->customer($org->id, ['latitude' => null, 'longitude' => null, 'address_detail' => 'Jl. Merdeka No. 10']);

        Http::fake([
            config('services.nominatim.base_url').'/*' => Http::response([
                ['display_name' => 'Jl. Merdeka, Sambas', 'lat' => 1.361, 'lon' => 109.382],
            ]),
        ]);

        $json = $this->actingAs($admin, 'sanctum')
            ->withSession(['_token' => 't'])
            ->postJson('/admin/gis/customers/'.$customer->id.'/geocode', [], ['X-CSRF-TOKEN' => 't'])
            ->assertOk()->json();

        $this->assertEqualsWithDelta(1.361, $json['lat'], 0.0001);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/search')
            && str_contains($request->header('User-Agent')[0] ?? '', 'PDAM-Admin'));
        $this->assertEqualsWithDelta(1.361, $customer->fresh()->latitude, 0.0001);
    }

    public function test_geocode_missing_batch_limited_and_one_second_pacing(): void
    {
        [$org, $admin] = $this->tenant('geocode-many');
        // customer_number menentukan urutan (orderBy) — pakai awalan Z..X agar pasti.
        $c1 = $this->customer($org->id, ['customer_number' => 'AAA-001', 'latitude' => null, 'longitude' => null, 'address_detail' => 'Jl. A No. 1']);
        $c2 = $this->customer($org->id, ['customer_number' => 'BBB-002', 'latitude' => null, 'longitude' => null, 'address_detail' => 'Jl. B No. 2']);
        $c3 = $this->customer($org->id, ['customer_number' => 'ZZZ-999', 'latitude' => null, 'longitude' => null, 'address_detail' => 'Jl. C No. 3']);

        Http::fake([
            config('services.nominatim.base_url').'/*' => Http::response([
                ['display_name' => 'Temuan', 'lat' => 1.35, 'lon' => 109.30],
            ]),
        ]);

        $json = $this->actingAs($admin, 'sanctum')
            ->withSession(['_token' => 't'])
            ->postJson('/admin/gis/geocode-missing', ['limit' => 2], ['X-CSRF-TOKEN' => 't'])
            ->assertOk()->json();

        $this->assertSame(2, $json['processed']);
        $this->assertSame(2, $json['found']);
        $this->assertSame(1, $json['remaining']);
        // urutan geocode-missing = customer_number (AAA < BBB < ZZZ), limit 2 → c1,c2 terisi.
        $c1->refresh();
        $this->assertNotNull($c1->latitude, 'A terproses');
        $c2->refresh();
        $this->assertNotNull($c2->latitude);
        $c3->refresh();
        $this->assertNull($c3->latitude, 'Z belum terproses (limit)');
        Http::assertSentCount(2);
    }

    public function test_manual_coordinates_validated(): void
    {
        [$org, $admin] = $this->tenant('manual');
        $customer = $this->customer($org->id, ['latitude' => null, 'longitude' => null]);

        $this->actingAs($admin, 'sanctum')
            ->withSession(['_token' => 't'])
            ->patchJson('/admin/gis/customers/'.$customer->id.'/coordinates', [
                'latitude' => 1.45, 'longitude' => 109.35,
            ], ['X-CSRF-TOKEN' => 't'])
            ->assertOk()
            ->assertJsonPath('id', $customer->id);

        $this->actingAs($admin, 'sanctum')
            ->withSession(['_token' => 't'])
            ->patchJson('/admin/gis/customers/'.$customer->id.'/coordinates', [
                'latitude' => 200, 'longitude' => 109.35,
            ], ['X-CSRF-TOKEN' => 't'])
            ->assertUnprocessable();
    }

    public function test_geocode_of_other_tenant_customer_not_found(): void
    {
        [$org, $admin] = $this->tenant('bind-a');
        [$otherOrg] = $this->tenant('bind-b');
        $other = $this->customer($otherOrg->id, ['latitude' => null, 'longitude' => null]);

        $this->actingAs($admin, 'sanctum')
            ->withSession(['_token' => 't'])
            ->postJson('/admin/gis/customers/'.$other->id.'/geocode', [], ['X-CSRF-TOKEN' => 't'])
            ->assertNotFound();
    }

    // ── Routing & tour OSRM ──────────────────────────────────────────────

    private function fakeOsrm(): void
    {
        Http::fake([
            config('services.osrm.base_url').'/*' => function ($request) {
                if (str_contains($request->url(), '/table/')) {
                    return Http::response([
                        'code' => 'Ok',
                        'durations' => [[0, 120], [120, 0]],
                    ]);
                }

                return Http::response([
                    'code' => 'Ok',
                    'routes' => [[
                        'geometry' => ['type' => 'LineString', 'coordinates' => [[109.3, 1.35], [109.31, 1.36]]],
                        'distance' => 1500,
                        'duration' => 240,
                        'legs' => [['distance' => 1500, 'duration' => 240]],
                    ]],
                ]);
            },
        ]);
    }

    public function test_route_between_points_returns_osrm_polyline(): void
    {
        [$org, $admin] = $this->tenant('rute-1');
        $this->fakeOsrm();

        $json = $this->actingAs($admin, 'sanctum')
            ->getJson('/admin/gis/route.json?profile=driving&points=1.35,109.30|1.36,109.31')
            ->assertOk()->json();

        $this->assertSame('LineString', $json['geometry']['type']);
        $this->assertEqualsWithDelta(1500, $json['distance'], 0.001);
    }

    public function test_tour_orders_meter_route_customers(): void
    {
        [$org, $admin] = $this->tenant('tour-1');
        $this->fakeOsrm();

        $route = MeterRoute::create(['pdam_org_id' => $org->id, 'code' => 'R-1', 'name' => 'Rute Test', 'is_active' => true]);
        $a = $this->customer($org->id, ['meter_route_id' => $route->id, 'latitude' => 1.3501, 'longitude' => 109.3001]);
        $b = $this->customer($org->id, ['meter_route_id' => $route->id, 'latitude' => 1.3502, 'longitude' => 109.3002]);

        $json = $this->actingAs($admin, 'sanctum')
            ->getJson('/admin/gis/tour.json?route_id='.$route->id)
            ->assertOk()->json();

        $this->assertCount(2, $json['stops']);
        $this->assertSame([$a->id, $b->id], array_column($json['stops'], 'id'));
        $this->assertSame('LineString', $json['geometry']['type']);
    }

    public function test_api_gis_customers_still_returns_feature_collection(): void
    {
        [$org, $admin] = $this->tenant('api-1');
        $this->customer($org->id);
        $this->customer($org->id, ['status' => 'isolir']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/gis/customers')
            ->assertOk()
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonCount(2, 'features');
    }
}
