<?php

namespace Tests\Feature;

use App\Jobs\GenerateScheduledReport;
use App\Models\Customer;
use App\Models\Module;
use App\Models\PdamOrganization;
use App\Models\ScheduledReport;
use App\Models\ScheduledReportRun;
use App\Models\SubscriptionModule;
use App\Models\User;
use App\Services\ReportExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScheduledReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_is_tenant_scoped_and_rejects_unknown_identifiers(): void
    {
        [$org, $admin] = $this->tenant('reports-a');
        [, $otherAdmin] = $this->tenant('reports-b');

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/bi/scheduled-reports', $this->payload())
            ->assertCreated()->assertJsonPath('data.pdam_org_id', $org->id);
        $id = $response->json('data.id');

        $this->actingAs($otherAdmin, 'sanctum')->getJson('/api/v1/bi/scheduled-reports')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($otherAdmin, 'sanctum')->putJson("/api/v1/bi/scheduled-reports/{$id}", $this->payload())->assertNotFound();
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/bi/scheduled-reports', [...$this->payload(), 'columns' => ['pdam_org_id']])
            ->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_COLUMN');
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/bi/scheduled-reports', [...$this->payload(), 'sort' => 'secret'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_SORT');
    }

    public function test_creation_rejects_locked_bi_and_locked_source_module(): void
    {
        [$org, $admin] = $this->tenant('locked', false);
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/bi/scheduled-reports', $this->payload())->assertForbidden()->assertJsonPath('error_code', 'MODULE_LOCKED');

        SubscriptionModule::create(['pdam_org_id' => $org->id, 'module_code' => 'BI', 'status' => 'active']);
        Module::create(['code' => 'CRM', 'name' => 'CRM', 'is_active' => true]);
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/bi/scheduled-reports', [
            ...$this->payload(), 'dataset' => 'complaints', 'columns' => ['ticket_number', 'status'],
        ])->assertForbidden()->assertJsonPath('error_code', 'MODULE_LOCKED');
    }

    public function test_due_dispatch_has_one_run_per_slot_and_only_tenant_rows_are_exported(): void
    {
        Storage::fake('private');
        [$org, $admin] = $this->tenant('dispatch');
        [$other] = $this->tenant('dispatch-other');
        Customer::create(['pdam_org_id' => $org->id, 'customer_number' => 'OWN-1', 'full_name' => 'Own', 'status' => 'active']);
        Customer::create(['pdam_org_id' => $other->id, 'customer_number' => 'OTHER-1', 'full_name' => 'Other', 'status' => 'active']);
        $report = ScheduledReport::create([
            ...$this->payload(), 'pdam_org_id' => $org->id, 'created_by' => $admin->id, 'next_run_at' => now()->subMinute(), 'is_active' => true,
        ]);

        $this->artisan('reports:dispatch-scheduled')->assertSuccessful();
        $this->artisan('reports:dispatch-scheduled')->assertSuccessful();
        $this->assertDatabaseCount('scheduled_report_runs', 1);
        $run = ScheduledReportRun::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame('completed', $run->status);
        $content = Storage::disk('private')->get($run->artifact_path);
        $this->assertStringContainsString('OWN-1', $content);
        $this->assertStringNotContainsString('OTHER-1', $content);
        $this->assertTrue($report->fresh()->next_run_at->isFuture());
    }

    public function test_run_now_generates_private_artifact_and_download_is_tenant_protected(): void
    {
        Storage::fake('private');
        [, $admin] = $this->tenant('download');
        [, $otherAdmin] = $this->tenant('download-other');
        $report = $this->createReport($admin);

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/bi/scheduled-reports/{$report->id}/run")->assertAccepted();
        $run = ScheduledReportRun::query()->withoutGlobalScopes()->firstOrFail();
        Storage::disk('private')->assertExists($run->artifact_path);
        $this->actingAs($admin, 'sanctum')->get("/api/v1/bi/scheduled-reports/{$report->id}/runs/{$run->id}/download")->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($otherAdmin, 'sanctum')->get("/api/v1/bi/scheduled-reports/{$report->id}/runs/{$run->id}/download")->assertNotFound();
    }

    public function test_execution_rechecks_bi_and_source_access(): void
    {
        Storage::fake('private');
        [$org, $admin] = $this->tenant('execution');
        $report = $this->createReport($admin);
        SubscriptionModule::query()->where('pdam_org_id', $org->id)->where('module_code', 'BI')->update(['status' => 'locked']);
        $run = ScheduledReportRun::create(['pdam_org_id' => $org->id, 'scheduled_report_id' => $report->id, 'scheduled_for' => now(), 'status' => 'queued']);

        app(GenerateScheduledReport::class, ['runId' => $run->id])->handle(app(ReportExportService::class));
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertNull($run->fresh()->artifact_path);
    }

    private function createReport(User $admin): ScheduledReport
    {
        return ScheduledReport::create([...$this->payload(), 'pdam_org_id' => $admin->pdam_org_id, 'created_by' => $admin->id, 'next_run_at' => now()->addDay(), 'is_active' => true]);
    }

    private function payload(): array
    {
        return ['name' => 'Daftar Pelanggan', 'dataset' => 'customers', 'format' => 'csv', 'columns' => ['customer_number', 'status'], 'filters' => [], 'sort' => 'customer_number', 'frequency' => 'daily', 'timezone' => 'Asia/Jakarta', 'local_time' => '08:00', 'day_of_week' => null, 'day_of_month' => null, 'is_active' => true];
    }

    private function tenant(string $code, bool $biActive = true): array
    {
        $org = PdamOrganization::create(['code' => $code, 'name' => $code, 'subscription_status' => 'active']);
        $admin = User::create(['pdam_org_id' => $org->id, 'name' => $code, 'email' => $code.'@test.local', 'password' => 'password', 'is_tenant_admin' => true, 'is_active' => true]);
        Module::firstOrCreate(['code' => 'BI'], ['name' => 'BI', 'is_active' => true]);
        if ($biActive) {
            SubscriptionModule::create(['pdam_org_id' => $org->id, 'module_code' => 'BI', 'status' => 'active']);
        }

        return [$org, $admin];
    }
}
