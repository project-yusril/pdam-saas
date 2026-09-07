<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Module;
use App\Models\PdamOrganization;
use App\Models\SubscriptionModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Export native PDF/XLSX (roadmap PRD / M-08). Kontrak jujur: file berisi
 * byte riil (bukan CSV berlabel xlsx), dikirim base64 via API, dengan
 * content-type + ekstensi yang sesuai.
 */
class NativeExportFormatTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $org = PdamOrganization::create([
            'code' => 'export-'.uniqid(), 'name' => 'PDAM Export', 'subscription_status' => 'active',
        ]);
        $admin = User::create([
            'pdam_org_id' => $org->id, 'name' => 'Export Admin',
            'email' => uniqid().'@export.test', 'password' => 'password',
            'is_tenant_admin' => true, 'is_active' => true,
        ]);
        Module::create(['code' => 'BI', 'name' => 'BI', 'is_active' => true]);
        SubscriptionModule::create(['pdam_org_id' => $org->id, 'module_code' => 'BI', 'status' => 'active']);

        $customer = Customer::create([
            'pdam_org_id' => $org->id, 'customer_number' => 'EXP-001',
            'full_name' => 'Eksi Por', 'status' => 'active',
        ]);
        Bill::create([
            'pdam_org_id' => $org->id, 'customer_id' => $customer->id,
            'bill_number' => 'B-EXP-1', 'period' => '2026-06',
            'amount_due' => 25000, 'status' => 'unpaid',
        ]);

        return $admin;
    }

    public function test_xlsx_export_is_real_spreadsheet_binary(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/export', [
            'format' => 'xlsx',
            'table' => 'bills',
            'columns' => ['bill_number', 'period', 'status'],
        ])->assertOk()->assertJsonPath('format', 'xlsx')
            ->assertJsonPath('content_type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $binary = base64_decode((string) $response->json('content_base64'), true);
        $this->assertNotFalse($binary);
        $this->assertStringStartsWith('PK', $binary, 'XLSX wajib ZIP container nyata');
        $this->assertStringEndsWith('.xlsx', $response->json('filename'));
    }

    public function test_pdf_export_is_real_pdf_bytes(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/export', [
            'format' => 'pdf',
            'table' => 'customers',
            'title' => 'Daftar Pelanggan Uji',
        ])->assertOk()->assertJsonPath('format', 'pdf')->assertJsonPath('content_type', 'application/pdf');

        $binary = base64_decode((string) $response->json('content_base64'), true);
        $this->assertStringStartsWith('%PDF-', $binary);
        $this->assertStringEndsWith('.pdf', $response->json('filename'));
    }

    public function test_scheduled_report_accepts_native_formats(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/bi/scheduled-reports', [
            'name' => 'Laporan XLSX Uji',
            'dataset' => 'bills',
            'format' => 'xlsx',
            'columns' => ['bill_number', 'period'],
            'frequency' => 'daily',
            'timezone' => 'Asia/Jakarta',
            'local_time' => '06:00',
        ]);

        $this->assertTrue(in_array($response->status(), [201, 422], true));
        if ($response->status() === 422) {
            $this->fail('format xlsx seharusnya diterima: '.json_encode($response->json('error')));
        }
        $response->assertCreated()->assertJsonPath('data.format', 'xlsx');
    }

    public function test_docx_export_is_real_wordprocessingml_container(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/export', [
            'format' => 'doc',
            'table' => 'customers',
            'title' => 'Daftar Pelanggan Uji DOC',
        ])->assertOk()->assertJsonPath('format', 'doc')
            ->assertJsonPath('content_type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $binary = base64_decode((string) $response->json('content_base64'), true);
        $this->assertNotFalse($binary);
        $this->assertStringStartsWith('PK', $binary, 'DOCX wajib ZIP container');
        $this->assertStringContainsString('[Content_Types].xml', $binary);
        $this->assertStringContainsString('word/document.xml', $binary);
        $this->assertStringEndsWith('.docx', $response->json('filename'));
    }
}
