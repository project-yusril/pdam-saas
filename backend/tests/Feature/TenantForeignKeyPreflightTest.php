<?php

namespace Tests\Feature;

use App\Models\PdamOrganization;
use App\Models\User;
use App\Support\TenantForeignKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class TenantForeignKeyPreflightTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_reports_orphans_and_cross_tenant_actors_without_mutation(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('The no-mutation preflight fixture uses SQLite constraint toggling.');
        }

        DB::rollBack();
        Schema::disableForeignKeyConstraints();

        $one = PdamOrganization::create(['code' => 'preflight-one', 'name' => 'One']);
        $two = PdamOrganization::create(['code' => 'preflight-two', 'name' => 'Two']);
        $actor = User::create([
            'pdam_org_id' => $two->id,
            'name' => 'Cross tenant',
            'email' => 'cross@test.local',
            'password' => 'password',
            'is_active' => true,
        ]);

        DB::table('reading_periods')->insert([
            'id' => 7001,
            'pdam_org_id' => $one->id,
            'period' => '2026-04',
            'status' => 'open',
            'opened_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('zones')->insert([
            'id' => 7002,
            'pdam_org_id' => 999999,
            'code' => 'ORPHAN',
            'name' => 'Orphan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        try {
            TenantForeignKeys::assertClean();
            $this->fail('Preflight should abort.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('zones.pdam_org_id orphan rows [7002]', $exception->getMessage());
            $this->assertStringContainsString('reading_periods.opened_by invalid actors [7001', $exception->getMessage());
            $this->assertStringContainsString('no business data was changed', $exception->getMessage());
        }

        $this->assertDatabaseHas('zones', ['id' => 7002, 'pdam_org_id' => 999999]);
        $this->assertDatabaseHas('reading_periods', ['id' => 7001, 'opened_by' => $actor->id]);

        Schema::disableForeignKeyConstraints();
        DB::table('zones')->where('id', 7002)->delete();
        DB::table('reading_periods')->where('id', 7001)->delete();
        DB::table('users')->where('id', $actor->id)->delete();
        DB::table('pdam_organizations')->whereIn('id', [$one->id, $two->id])->delete();
        Schema::enableForeignKeyConstraints();
        DB::beginTransaction();
    }
}
