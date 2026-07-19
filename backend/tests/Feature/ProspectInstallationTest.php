<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\CustomerProspect;
use App\Models\JournalEntry;
use App\Models\Module;
use App\Models\PdamOrganization;
use App\Models\SubscriptionModule;
use App\Services\CustomerLifecycleService;
use App\Services\InstallationService;
use App\Services\KtpOcrService;
use App\Services\PaymentService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifikasi alur Fase 2 (PRD 3.1):
 * - OCR KTP mem-parse teks mentah menjadi field terstruktur.
 * - Pembayaran pemasangan memicu jurnal Kas↔Pendapatan Pemasangan (balance).
 * - Prospek lunas → payment_paid → schedule → installed → active (Customer dibuat).
 * - Lifecycle: isolir → sambung kembali, dan balik nama.
 */
class ProspectInstallationTest extends TestCase
{
    use RefreshDatabase;

    private int $orgId;

    protected function setUp(): void
    {
        parent::setUp();
        $org = PdamOrganization::create([
            'code' => 'installation-test',
            'name' => 'PDAM Installation Test',
            'subscription_status' => 'active',
        ]);
        $this->orgId = $org->id;
        TenantContext::set($this->orgId);

        foreach ([
            ['1-001', 'Kas/Bank', 'ASSET', 'DEBIT'],
            ['4-002', 'Pendapatan Pemasangan Baru', 'REVENUE', 'KREDIT'],
        ] as [$c, $n, $t, $b]) {
            ChartOfAccount::create(['pdam_org_id' => $this->orgId, 'code' => $c, 'name' => $n, 'type' => $t, 'normal_balance' => $b]);
        }
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_ocr_parses_ktp_raw_text(): void
    {
        $raw = "PROVINSI KALIMANTAN BARAT\n"
            ."NIK : 6171012345678901\n"
            ."Nama : BUDI SANTOSO\n"
            ."Tempat/Tgl Lahir : PONTIANAK, 17-08-1990\n"
            ."Alamat : JL MERDEKA NO 10\n";

        $parsed = app(KtpOcrService::class)->parse($raw);

        $this->assertEquals('6171012345678901', $parsed['nik']);
        $this->assertEquals('BUDI SANTOSO', $parsed['full_name']);
        $this->assertEquals('PONTIANAK', $parsed['birth_place']);
        $this->assertEquals('1990-08-17', $parsed['birth_date']);
        $this->assertGreaterThan(0, $parsed['confidence']);
    }

    public function test_ocr_parses_additional_ektp_fields(): void
    {
        $raw = "PROVINSI KALIMANTAN BARAT\n"
            ."NIK : 6171012345678901\n"
            ."Nama : BUDI SANTOSO\n"
            ."Tempat/Tgl Lahir : PONTIANAK, 17-08-1990\n"
            ."Jenis Kelamin : LAKI-LAKI Gol. Darah : O\n"
            ."Alamat : JL MERDEKA NO 10\n"
            ."RT/RW : 001/002\n"
            ."Agama : ISLAM\n"
            ."Status Perkawinan : KAWIN\n"
            ."Pekerjaan : WIRASWASTA\n"
            ."Kewarganegaraan : WNI\n";

        $parsed = app(KtpOcrService::class)->parse($raw);

        $this->assertEquals('L', $parsed['gender']);
        $this->assertEquals('Islam', $parsed['religion']);
        $this->assertEquals('kawin', $parsed['marital_status']);
        $this->assertEquals('WIRASWASTA', $parsed['occupation']);
        $this->assertEquals('WNI', $parsed['nationality']);
        $this->assertEquals('001', $parsed['rt']);
        $this->assertEquals('002', $parsed['rw']);
    }

    public function test_order_materials_stub_when_wh_inactive(): void
    {
        $prospect = $this->makePendingPaymentProspect();
        $payments = app(PaymentService::class);
        $installer = app(InstallationService::class);

        // Lunas → payment_paid (belum ada modul WH aktif untuk tenant test)
        $payments->markPaid($payments->createForInstallation($prospect), 'TRX-MAT-1');

        $result = $installer->orderMaterials($prospect->fresh());

        $this->assertEquals('planning_only', $result['mode']);
        $this->assertFalse($result['warehouse_module_active']);
        $this->assertFalse($result['stock_reserved']);
        $this->assertFalse($result['stock_issued']);
        $this->assertFalse($result['accounting_posted']);
        $this->assertIsArray($result['materials']);
    }

    public function test_order_materials_with_wh_active_still_reports_no_stock_mutation(): void
    {
        $prospect = $this->makePendingPaymentProspect();
        $payments = app(PaymentService::class);
        $payments->markPaid($payments->createForInstallation($prospect), 'TRX-MAT-WH');
        Module::create(['code' => 'WH', 'name' => 'Warehouse', 'is_active' => true]);
        SubscriptionModule::create([
            'pdam_org_id' => $this->orgId,
            'module_code' => 'WH',
            'status' => 'active',
        ]);

        $result = app(InstallationService::class)->orderMaterials($prospect->fresh());

        $this->assertEquals('planning_only', $result['mode']);
        $this->assertTrue($result['warehouse_module_active']);
        $this->assertFalse($result['stock_reserved']);
        $this->assertFalse($result['stock_issued']);
        $this->assertFalse($result['accounting_posted']);
    }

    private function makePendingPaymentProspect(): CustomerProspect
    {
        return CustomerProspect::create([
            'pdam_org_id' => $this->orgId,
            'registration_number' => 'REG-TEST01',
            'nik' => '6171012345678901',
            'full_name' => 'Budi Santoso',
            'installation_address' => 'Jl Merdeka No 10',
            'status' => 'payment_pending',
            'installation_fee' => 500000,
            'payment_due_at' => now()->addHours(12),
        ]);
    }

    public function test_full_flow_payment_to_active_customer(): void
    {
        $prospect = $this->makePendingPaymentProspect();
        $payments = app(PaymentService::class);
        $installer = app(InstallationService::class);

        // Bayar biaya pemasangan → jurnal balance & prospek payment_paid
        $payment = $payments->createForInstallation($prospect);
        $paid = $payments->markPaid($payment, 'TRX-INSTALL-1');
        $this->assertEquals('success', $paid->status);
        $this->assertEquals('payment_paid', $prospect->fresh()->status);

        $entry = JournalEntry::where('reference_type', 'INSTALLATION_FEE')
            ->where('reference_id', $payment->id)->first();
        $this->assertNotNull($entry);
        $this->assertEquals(
            (float) $entry->lines->where('type', 'DEBIT')->sum('amount'),
            (float) $entry->lines->where('type', 'KREDIT')->sum('amount'),
        );

        // Jadwalkan → selesai → aktivasi
        $schedule = $installer->schedule($prospect->fresh(), now()->addDay()->toDateString(), 42);
        $this->assertEquals('installation_scheduled', $prospect->fresh()->status);

        $installer->complete($schedule);
        $this->assertEquals('installed', $prospect->fresh()->status);

        $customer = $installer->activate($prospect->fresh(), ['meter_serial_number' => 'MTR-001']);
        $this->assertEquals('active', $customer->status);
        $this->assertEquals(0, (int) $customer->initial_reading);
        $this->assertEquals('active', $prospect->fresh()->status);
    }

    public function test_cannot_schedule_before_payment(): void
    {
        $prospect = $this->makePendingPaymentProspect();

        $this->expectException(RuntimeException::class);
        app(InstallationService::class)->schedule($prospect, now()->toDateString(), 1);
    }

    public function test_lifecycle_disconnect_and_reconnect(): void
    {
        $prospect = $this->makePendingPaymentProspect();
        $payments = app(PaymentService::class);
        $installer = app(InstallationService::class);

        $payments->markPaid($payments->createForInstallation($prospect), 'TRX-2');
        $schedule = $installer->schedule($prospect->fresh(), now()->toDateString(), 1);
        $installer->complete($schedule);
        $customer = $installer->activate($prospect->fresh());

        $lifecycle = app(CustomerLifecycleService::class);

        $lifecycle->disconnect($customer, 'isolir', 'Tunggakan 3 bulan', 7);
        $this->assertEquals('isolir', $customer->fresh()->status);

        $reconnection = $lifecycle->reconnect($customer->fresh(), 150000, 7);
        $this->assertEquals(150000, (float) $reconnection->fee);
        $this->assertEquals('active', $customer->fresh()->status);
    }

    public function test_lifecycle_ownership_transfer_updates_customer(): void
    {
        $prospect = $this->makePendingPaymentProspect();
        $payments = app(PaymentService::class);
        $installer = app(InstallationService::class);

        $payments->markPaid($payments->createForInstallation($prospect), 'TRX-3');
        $schedule = $installer->schedule($prospect->fresh(), now()->toDateString(), 1);
        $installer->complete($schedule);
        $customer = $installer->activate($prospect->fresh());

        app(CustomerLifecycleService::class)->transferOwnership($customer, [
            'new_owner_name' => 'Siti Aminah',
            'new_owner_phone' => '08123456789',
        ], 7);

        $this->assertEquals('Siti Aminah', $customer->fresh()->full_name);
        $this->assertEquals('08123456789', $customer->fresh()->phone);
    }
}
