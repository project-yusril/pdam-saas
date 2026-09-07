<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Module;
use App\Models\PdamOrganization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SubscriptionModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_bi_rejects_unknown_dimension_metric_and_filter(): void
    {
        [, $admin] = $this->biAdmin();

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/bi/report-builder', [
            'table' => 'bills',
            'dimensions' => ['status, (SELECT 1)'],
            'metrics' => ['sum:amount_due'],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_DIMENSION');

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/bi/report-builder', [
            'table' => 'bills',
            'dimensions' => ['status'],
            'metrics' => ['sleep:amount_due'],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_METRIC');

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/bi/report-builder', [
            'table' => 'bills',
            'dimensions' => ['status'],
            'metrics' => ['sum:amount_due'],
            'filters' => ['pdam_org_id' => 999],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_FILTER');
    }

    public function test_bi_executes_only_allowlisted_aggregation(): void
    {
        [$org, $admin] = $this->biAdmin();
        $customer = Customer::create([
            'pdam_org_id' => $org->id,
            'customer_number' => 'RPT-001',
            'full_name' => 'Report Customer',
            'status' => 'active',
        ]);
        Bill::create([
            'pdam_org_id' => $org->id,
            'customer_id' => $customer->id,
            'bill_number' => 'B-RPT-001',
            'period' => '2026-07',
            'amount_due' => 125000,
            'status' => 'paid',
        ]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/bi/report-builder', [
            'table' => 'bills',
            'dimensions' => ['status'],
            'metrics' => ['sum:amount_due'],
        ])->assertOk()
            ->assertJsonPath('data.rows.0.status', 'paid')
            ->assertJsonPath('data.rows.0.sum_amount_due', 125000);
    }

    public function test_export_rejects_sensitive_or_unknown_identifiers(): void
    {
        [, $admin] = $this->biAdmin();

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/export', [
            'format' => 'csv',
            'table' => 'customers',
            'columns' => ['customer_number', 'pdam_org_id'],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_COLUMN');

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/export', [
            'format' => 'csv',
            'table' => 'customers',
            'sort' => 'internal_secret_column',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_SORT');
    }

    public function test_bi_permission_matches_seeded_permission_for_non_admin(): void
    {
        [$org] = $this->biAdmin();
        $analyst = User::create([
            'pdam_org_id' => $org->id,
            'name' => 'Data Analyst',
            'email' => uniqid().'@analyst.test',
            'password' => 'password',
            'is_tenant_admin' => false,
            'is_active' => true,
        ]);
        $permission = Permission::create([
            'code' => 'bi.view.view',
            'module_code' => 'BI',
            'resource' => 'view',
            'action' => 'view',
        ]);
        $role = Role::create([
            'pdam_org_id' => $org->id,
            'code' => 'data_analyst',
            'name' => 'Data Analyst',
        ]);
        $role->permissions()->attach($permission);
        $analyst->roles()->attach($role);

        $this->actingAs($analyst, 'sanctum')->postJson('/api/v1/bi/report-builder', [
            'table' => 'bills',
            'dimensions' => ['status'],
            'metrics' => ['count:id'],
        ])->assertOk();
    }

    public function test_bi_and_export_reject_locked_source_module_for_admin(): void
    {
        [, $admin] = $this->biAdmin();

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/bi/report-builder', [
            'table' => 'complaints',
            'dimensions' => ['status'],
            'metrics' => ['count:id'],
        ])->assertForbidden()->assertJsonPath('error_code', 'MODULE_LOCKED');

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/export', [
            'format' => 'csv',
            'table' => 'employees',
        ])->assertForbidden()->assertJsonPath('error_code', 'MODULE_LOCKED');
    }

    public function test_report_inputs_reject_nested_filters_empty_columns_and_invalid_metric_pairs(): void
    {
        [, $admin] = $this->biAdmin();

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/bi/report-builder', [
            'table' => 'bills',
            'dimensions' => ['status'],
            'metrics' => ['sum:id'],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_METRIC');

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/bi/report-builder', [
            'table' => 'bills',
            'dimensions' => ['status'],
            'metrics' => ['count:id'],
            'filters' => ['status' => [['paid']]],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_FILTER_VALUE');

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/export', [
            'format' => 'csv',
            'table' => 'bills',
            'columns' => [],
        ])->assertUnprocessable();
    }

    public function test_export_defaults_work_without_sort_and_csv_formulas_are_neutralized(): void
    {
        [$org, $admin] = $this->biAdmin();
        Customer::create([
            'pdam_org_id' => $org->id,
            'customer_number' => '=1+1',
            'full_name' => 'Sensitive Name',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/export', [
            'format' => 'csv',
            'table' => 'customers',
            'title' => '=HYPERLINK("https://example.test")',
        ])->assertOk()
            ->assertJsonMissing(['full_name'])
            ->assertJsonPath('format', 'csv')
            ->assertJsonPath('content_type', 'text/csv')
            ->assertJsonPath('total_rows', 1);

        $this->assertStringContainsString("'=1+1", $response->json('data'));
        $this->assertStringContainsString("'=HYPERLINK", $response->json('data'));
        $this->assertStringEndsWith('.csv', $response->json('filename'));
    }

    public function test_export_html_is_truthfully_named_and_legacy_formats_are_rejected(): void
    {
        [, $admin] = $this->biAdmin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/export', [
            'format' => 'html',
            'table' => 'customers',
            'title' => 'A&B <active>',
        ])->assertOk()
            ->assertJsonPath('format', 'html')
            ->assertJsonPath('content_type', 'text/html');

        $this->assertStringEndsWith('.html', $response->json('filename'));
        $this->assertStringStartsWith('<!DOCTYPE html>', $response->json('data'));
        $this->assertStringNotContainsString('A&B <active>', $response->json('data'));
        $this->assertStringContainsString('A&amp;B &lt;active&gt;', $response->json('data'));

        foreach (['excel', 'xls', 'pptx'] as $unsupportedFormat) {
            $this->actingAs($admin, 'sanctum')->postJson('/api/v1/export', [
                'format' => $unsupportedFormat,
                'table' => 'customers',
            ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        }

        // 'doc' kini native (PhpWord .docx) — bukan lagi format yang ditolak.
        $doc = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/export', [
            'format' => 'doc',
            'table' => 'customers',
        ])->assertOk()->assertJsonPath('format', 'doc')
            ->assertJsonPath('content_type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertJsonPath('total_rows', 0)
            ->assertJsonPath('filename', fn ($value) => str_ends_with($value, '.docx'));
    }

    private function biAdmin(): array
    {
        $org = PdamOrganization::create([
            'code' => 'report-'.uniqid(),
            'name' => 'PDAM Report',
            'subscription_status' => 'active',
        ]);
        $admin = User::create([
            'pdam_org_id' => $org->id,
            'name' => 'Report Admin',
            'email' => uniqid().'@report.test',
            'password' => 'password',
            'is_tenant_admin' => true,
            'is_active' => true,
        ]);
        Module::create(['code' => 'BI', 'name' => 'BI', 'is_active' => true]);
        SubscriptionModule::create([
            'pdam_org_id' => $org->id,
            'module_code' => 'BI',
            'status' => 'active',
        ]);

        return [$org, $admin];
    }
}
