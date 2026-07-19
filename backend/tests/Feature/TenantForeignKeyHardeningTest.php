<?php

namespace Tests\Feature;

use App\Models\MaintenanceRecord;
use App\Models\MaintenanceSchedule;
use App\Models\Meter;
use App\Models\MeterAnomaly;
use App\Models\MeterLifecycleEvent;
use App\Models\MeterReading;
use App\Models\MeterReplacement;
use App\Models\MeterRoute;
use App\Models\MeterRouteAssignment;
use App\Models\PdamOrganization;
use App\Models\ReadingPeriod;
use App\Models\SaasInvoice;
use App\Models\SaasPurchaseOrder;
use App\Models\User;
use App\Support\TenantForeignKeys;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantForeignKeyHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_identified_tenant_foreign_keys_have_restrict_metadata(): void
    {
        $this->assertCount(97, TenantForeignKeys::TABLES);
        $this->assertCount(97, array_unique(TenantForeignKeys::TABLES));

        foreach (TenantForeignKeys::TABLES as $number => $table) {
            $foreign = collect(Schema::getForeignKeys($table))->first(
                fn (array $key): bool => $key['columns'] === ['pdam_org_id']
                    && $key['foreign_table'] === 'pdam_organizations',
            );

            $this->assertNotNull($foreign, $table.' tenant FK missing');
            $this->assertSame('restrict', strtolower($foreign['on_delete']), $table.' must restrict tenant deletion');
            if (DB::getDriverName() === 'mysql') {
                $this->assertSame('tfk_'.str_pad((string) ($number + 1), 3, '0', STR_PAD_LEFT), $foreign['name']);
            }
        }
    }

    public function test_invalid_tenant_is_rejected_and_nullable_global_is_preserved(): void
    {
        DB::table('streets')->insert([
            'pdam_org_id' => null,
            'village_id' => $this->villageId(),
            'name' => 'Global Street',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertDatabaseHas('streets', ['name' => 'Global Street', 'pdam_org_id' => null]);

        $this->expectException(QueryException::class);
        DB::table('zones')->insert([
            'pdam_org_id' => 999999,
            'code' => 'BAD',
            'name' => 'Invalid tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_tenant_delete_is_restricted_while_business_rows_exist(): void
    {
        $org = $this->organization('restrict');
        DB::table('zones')->insert([
            'pdam_org_id' => $org->id,
            'code' => 'Z1',
            'name' => 'Zone 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            DB::table('pdam_organizations')->where('id', $org->id)->delete();
            $this->fail('Tenant deletion should be restricted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('pdam_organizations', ['id' => $org->id]);
            $this->assertDatabaseHas('zones', ['pdam_org_id' => $org->id]);
        }
    }

    public function test_actor_foreign_keys_are_composite_restrict_constraints(): void
    {
        $this->assertCount(8, TenantForeignKeys::ACTORS);
        $userUnique = collect(Schema::getIndexes('users'))->first(
            fn (array $index): bool => $index['columns'] === ['pdam_org_id', 'id'] && $index['unique'],
        );
        $this->assertNotNull($userUnique);

        foreach (TenantForeignKeys::ACTORS as [$table, $column]) {
            $foreign = collect(Schema::getForeignKeys($table))->first(
                fn (array $key): bool => $key['columns'] === ['pdam_org_id', $column]
                    && $key['foreign_table'] === 'users'
                    && $key['foreign_columns'] === ['pdam_org_id', 'id'],
            );
            $this->assertNotNull($foreign, $table.'.'.$column.' actor FK missing');
            $this->assertSame('restrict', strtolower($foreign['on_delete']));
        }
    }

    public function test_cross_tenant_and_missing_meter_actors_are_rejected_but_null_is_allowed(): void
    {
        $one = $this->organization('actor-one');
        $two = $this->organization('actor-two');
        $otherActor = $this->user($two, 'other');

        DB::table('reading_periods')->insert([
            'pdam_org_id' => $one->id,
            'period' => '2026-01',
            'status' => 'open',
            'opened_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$otherActor->id, 999999] as $actorId) {
            try {
                DB::table('reading_periods')->insert([
                    'pdam_org_id' => $one->id,
                    'period' => '2026-'.($actorId === $otherActor->id ? '02' : '03'),
                    'status' => 'open',
                    'opened_by' => $actorId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->fail('Invalid meter actor should be rejected.');
            } catch (QueryException) {
                $this->assertDatabaseMissing('reading_periods', ['pdam_org_id' => $one->id, 'opened_by' => $actorId]);
            }
        }
    }

    public function test_models_autoload_and_active_commerce_and_meter_relations(): void
    {
        $this->assertSame('App\\Models\\MaintenanceRecord', (new MaintenanceRecord)->getMorphClass());
        $this->assertSame('maintenance_records', (new MaintenanceRecord)->getTable());
        $this->assertSame('saas_invoices', (new SaasInvoice)->getTable());
        $this->assertSame(MaintenanceSchedule::class, (new MaintenanceRecord)->schedule()->getRelated()::class);
        $this->assertSame(PdamOrganization::class, (new SaasInvoice)->organization()->getRelated()::class);

        $organization = new PdamOrganization;
        $this->assertSame(SaasInvoice::class, $organization->saasInvoices()->getRelated()::class);
        $this->assertSame(SaasPurchaseOrder::class, $organization->saasPurchaseOrders()->getRelated()::class);
        $this->assertSame(PdamOrganization::class, (new SaasPurchaseOrder)->organization()->getRelated()::class);
        $this->assertSame(User::class, (new SaasPurchaseOrder)->requester()->getRelated()::class);

        $this->assertSame(User::class, (new MeterRouteAssignment)->officer()->getRelated()::class);
        $this->assertSame(MeterRoute::class, (new MeterRouteAssignment)->route()->getRelated()::class);
        $this->assertSame(User::class, (new ReadingPeriod)->openedBy()->getRelated()::class);
        $this->assertSame(User::class, (new ReadingPeriod)->closedBy()->getRelated()::class);
        $this->assertSame(User::class, (new MeterReading)->reader()->getRelated()::class);
        $this->assertSame(User::class, (new MeterReading)->verifier()->getRelated()::class);
        $this->assertSame(User::class, (new MeterReplacement)->processor()->getRelated()::class);
        $this->assertSame(User::class, (new MeterLifecycleEvent)->performer()->getRelated()::class);
        $this->assertSame(User::class, (new MeterAnomaly)->reviewer()->getRelated()::class);
        $this->assertSame(MeterAnomaly::class, (new Meter)->anomalies()->getRelated()::class);
    }

    private function organization(string $code): PdamOrganization
    {
        return PdamOrganization::create(['code' => $code, 'name' => $code, 'subscription_status' => 'active']);
    }

    private function user(PdamOrganization $organization, string $code): User
    {
        return User::create([
            'pdam_org_id' => $organization->id,
            'name' => $code,
            'email' => $code.'@test.local',
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    private function villageId(): int
    {
        $province = DB::table('provinces')->insertGetId(['code' => 'P1', 'name' => 'Province', 'created_at' => now(), 'updated_at' => now()]);
        $city = DB::table('cities')->insertGetId(['province_id' => $province, 'code' => 'C1', 'name' => 'City', 'created_at' => now(), 'updated_at' => now()]);
        $district = DB::table('districts')->insertGetId(['city_id' => $city, 'code' => 'D1', 'name' => 'District', 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('villages')->insertGetId(['district_id' => $district, 'code' => 'V1', 'name' => 'Village', 'created_at' => now(), 'updated_at' => now()]);
    }
}
