<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerProspect;
use App\Models\MeterRoute;
use App\Models\MeterRouteAssignment;
use App\Models\Module;
use App\Models\PdamOrganization;
use App\Models\ReadingPeriod;
use App\Models\SubscriptionModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_meter_sync_accepts_zero_and_retry_is_idempotent(): void
    {
        [$org, $officer, $customer] = $this->meterFixture();
        $uuid = (string) Str::uuid();
        $payload = [
            'type' => 'meter_readings',
            'payloads' => [[
                'client_uuid' => $uuid,
                'customer_id' => $customer->id,
                'period' => '2026-07',
                'reading_value' => 0,
                'reading_date' => '2026-07-15',
                'reading_type' => 'offline_sync',
            ]],
        ];

        $this->actingAs($officer, 'sanctum')->postJson('/api/v1/sync/upload', $payload)
            ->assertOk()
            ->assertJsonPath('data.synced', 1)
            ->assertJsonPath('data.results.0.status', 'created')
            ->assertJsonPath('data.results.0.client_uuid', $uuid);

        $this->actingAs($officer, 'sanctum')->postJson('/api/v1/sync/upload', $payload)
            ->assertOk()
            ->assertJsonPath('data.synced', 1)
            ->assertJsonPath('data.results.0.status', 'duplicate');

        $this->assertDatabaseCount('meter_readings', 1);
        $this->assertDatabaseHas('meter_readings', [
            'pdam_org_id' => $org->id,
            'client_uuid' => $uuid,
            'reading_value' => 0,
            'read_by' => $officer->id,
        ]);
    }

    public function test_meter_sync_rejects_unassigned_route_and_closed_period(): void
    {
        [, $officer, $customer] = $this->meterFixture();
        MeterRouteAssignment::where('officer_id', $officer->id)->update(['is_active' => false]);

        $response = $this->actingAs($officer, 'sanctum')->postJson('/api/v1/sync/upload', [
            'type' => 'meter_readings',
            'payloads' => [[
                'client_uuid' => (string) Str::uuid(),
                'customer_id' => $customer->id,
                'period' => '2026-07',
                'reading_value' => 12,
                'reading_date' => '2026-07-15',
                'reading_type' => 'manual_corrected',
            ]],
        ]);
        $response->assertOk()->assertJsonPath('data.results.0.error_code', 'ROUTE_NOT_ASSIGNED');

        ReadingPeriod::where('period', '2026-07')->update(['status' => 'closed']);
        $response = $this->actingAs($officer, 'sanctum')->postJson('/api/v1/sync/upload', [
            'type' => 'meter_readings',
            'payloads' => [[
                'client_uuid' => (string) Str::uuid(),
                'customer_id' => $customer->id,
                'period' => '2026-07',
                'reading_value' => 12,
                'reading_date' => '2026-07-15',
                'reading_type' => 'manual_corrected',
            ]],
        ]);
        $response->assertOk()->assertJsonPath('data.results.0.error_code', 'PERIOD_CLOSED');
        $this->assertDatabaseCount('meter_readings', 0);
    }

    public function test_survey_sync_is_supported_idempotent_and_updates_prospect(): void
    {
        [$org, $surveyor] = $this->tenantUser();
        $this->activate($org, 'SRV');
        $prospect = CustomerProspect::create([
            'pdam_org_id' => $org->id,
            'registration_number' => 'SYNC-SRV-001',
            'full_name' => 'Survey Sync',
            'installation_address' => 'Jl. Sync',
            'status' => 'surveying',
            'assigned_surveyor_id' => $surveyor->id,
        ]);
        $uuid = (string) Str::uuid();
        $payload = [
            'type' => 'survey_reports',
            'payloads' => [[
                'client_uuid' => $uuid,
                'prospect_id' => $prospect->id,
                'photo_house_urls' => [
                    $org->id.'/survey/a.jpg',
                    $org->id.'/survey/b.jpg',
                ],
                'latitude' => -6.2,
                'longitude' => 106.8,
                'recommendation' => 'feasible',
                'land_status' => 'milik_sendiri',
            ]],
        ];

        $this->actingAs($surveyor, 'sanctum')->postJson('/api/v1/sync/upload', $payload)
            ->assertOk()->assertJsonPath('data.results.0.status', 'created');
        $this->actingAs($surveyor, 'sanctum')->postJson('/api/v1/sync/upload', $payload)
            ->assertOk()->assertJsonPath('data.results.0.status', 'duplicate');

        $this->assertDatabaseCount('survey_reports', 1);
        $this->assertDatabaseHas('customer_prospects', [
            'id' => $prospect->id,
            'status' => 'survey_submitted',
            'location_source' => 'surveyor_verified',
        ]);
    }

    public function test_sync_download_and_status_return_real_server_state(): void
    {
        [$org, $surveyor] = $this->tenantUser();
        $this->activate($org, 'SRV');
        CustomerProspect::create([
            'pdam_org_id' => $org->id,
            'registration_number' => 'SYNC-SRV-002',
            'full_name' => 'Pending Survey',
            'installation_address' => 'Jl. Pending',
            'status' => 'surveying',
            'assigned_surveyor_id' => $surveyor->id,
        ]);
        ReadingPeriod::create(['pdam_org_id' => $org->id, 'period' => '2026-07', 'status' => 'open']);

        $this->actingAs($surveyor, 'sanctum')->getJson('/api/v1/sync/download')
            ->assertOk()->assertJsonCount(1, 'data.pending_survey_tasks');
        $this->actingAs($surveyor, 'sanctum')->getJson('/api/v1/sync/status')
            ->assertOk()
            ->assertJsonPath('data.pending_survey_tasks', 1)
            ->assertJsonPath('data.open_reading_periods.0', '2026-07');
    }

    private function meterFixture(): array
    {
        [$org, $officer] = $this->tenantUser();
        $this->activate($org, 'MTR');
        $route = MeterRoute::create([
            'pdam_org_id' => $org->id,
            'code' => 'R-SYNC',
            'name' => 'Route Sync',
        ]);
        MeterRouteAssignment::create([
            'pdam_org_id' => $org->id,
            'meter_route_id' => $route->id,
            'officer_id' => $officer->id,
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'pdam_org_id' => $org->id,
            'customer_number' => 'C-SYNC',
            'full_name' => 'Customer Sync',
            'meter_route_id' => $route->id,
            'initial_reading' => 0,
            'status' => 'active',
        ]);
        ReadingPeriod::create(['pdam_org_id' => $org->id, 'period' => '2026-07', 'status' => 'open']);

        return [$org, $officer, $customer];
    }

    private function tenantUser(): array
    {
        $org = PdamOrganization::create([
            'code' => 'sync-'.uniqid(),
            'name' => 'PDAM Sync',
            'subscription_status' => 'active',
        ]);
        $user = User::create([
            'pdam_org_id' => $org->id,
            'name' => 'Field User',
            'email' => uniqid().'@sync.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        return [$org, $user];
    }

    private function activate(PdamOrganization $org, string $code): void
    {
        Module::create(['code' => $code, 'name' => $code, 'is_active' => true]);
        SubscriptionModule::create([
            'pdam_org_id' => $org->id,
            'module_code' => $code,
            'status' => 'active',
        ]);
    }
}
