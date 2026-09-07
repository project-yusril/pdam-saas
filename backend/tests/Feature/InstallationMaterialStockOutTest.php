<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\CustomerProspect;
use App\Models\InstallationMaterialOrder;
use App\Models\JournalEntry;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\MaterialTransaction;
use App\Models\Module;
use App\Models\PdamOrganization;
use App\Models\SubscriptionModule;
use App\Models\SurveyReport;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\InstallationService;
use App\Services\PaymentService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Implementasi M-10/stock-out (temuan2.md + HANDOVER:195):
 * order material → reservasi stok → pemasangan selesai → stock-out + jurnal
 * kapitalisasi (DEBIT 1-004 / KREDIT 1-003) dengan SUM(D)=SUM(K).
 */
class InstallationMaterialStockOutTest extends TestCase
{
    use RefreshDatabase;

    private int $orgId;

    private CustomerProspect $prospect;

    private int $meterId;

    private int $pipeId;

    private int $warehouseId;

    protected function setUp(): void
    {
        parent::setUp();
        $org = PdamOrganization::create([
            'code' => 'material-test',
            'name' => 'PDAM Material Test',
            'subscription_status' => 'active',
        ]);
        $this->orgId = $org->id;
        TenantContext::set($this->orgId);

        Module::create(['code' => 'WH', 'name' => 'Warehouse', 'is_active' => true]);
        SubscriptionModule::create([
            'pdam_org_id' => $this->orgId,
            'module_code' => 'WH',
            'status' => 'active',
        ]);

        foreach ([
            ['1-001', 'Kas / Bank', 'ASSET', 'DEBIT'],
            ['1-003', 'Persediaan Material', 'ASSET', 'DEBIT'],
            ['1-004', 'Aset Jaringan / Instalasi', 'ASSET', 'DEBIT'],
            ['4-002', 'Pendapatan Pemasangan Baru', 'REVENUE', 'KREDIT'],
        ] as [$code, $name, $type, $balance]) {
            ChartOfAccount::create([
                'pdam_org_id' => $this->orgId,
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'normal_balance' => $balance,
                'is_active' => true,
            ]);
        }

        $this->prospect = $this->makePaidProspect();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function makePaidProspect(): CustomerProspect
    {
        $prospect = CustomerProspect::create([
            'pdam_org_id' => $this->orgId,
            'registration_number' => 'REG-MTL-01',
            'nik' => '6171012345678901',
            'full_name' => 'Joko Test',
            'installation_address' => 'Jl Test 1',
            'status' => 'payment_pending',
            'installation_fee' => 500000,
            'payment_due_at' => now()->addHours(2),
        ]);

        $payments = app(PaymentService::class);
        $payments->markPaid($payments->createForInstallation($prospect), 'TRX-MTL-1');

        return $prospect->fresh();
    }

    private function seedInventory(): void
    {
        $zone = Zone::create([
            'pdam_org_id' => $this->orgId,
            'code' => 'MTLZ',
            'name' => 'Wilayah Material',
            'is_main' => true,
        ]);
        $warehouse = Warehouse::create([
            'pdam_org_id' => $this->orgId,
            'zone_id' => $zone->id,
            'code' => 'WH-01',
            'name' => 'Gudang Utama',
            'warehouse_type' => 'main',
            'is_active' => true,
        ]);
        $this->warehouseId = $warehouse->id;

        $meter = Material::create([
            'pdam_org_id' => $this->orgId,
            'code' => 'MTL-MTR-05',
            'name' => 'Meter DN15',
            'category' => 'meter',
            'unit' => 'unit',
            'last_price' => 150000,
            'is_active' => true,
        ]);
        $pipe = Material::create([
            'pdam_org_id' => $this->orgId,
            'code' => 'MTL-PIPA-3',
            'name' => 'Pipa HDPE 3in',
            'category' => 'pipa',
            'unit' => 'm',
            'last_price' => 25000,
            'is_active' => true,
        ]);
        $this->meterId = $meter->id;
        $this->pipeId = $pipe->id;

        MaterialStock::create(['pdam_org_id' => $this->orgId, 'material_id' => $meter->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10, 'minimum_stock' => 2]);
        MaterialStock::create(['pdam_org_id' => $this->orgId, 'material_id' => $pipe->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 50, 'minimum_stock' => 10]);

        SurveyReport::create([
            'pdam_org_id' => $this->orgId,
            'prospect_id' => $this->prospect->id,
            'surveyor_id' => 7,
            'estimated_materials' => [
                ['material_id' => $meter->id, 'qty' => 1],
                ['material_code' => 'MTL-PIPA-3', 'qty' => 4],
                ['material_id' => 99999, 'qty' => 1], // tak terselesaikan → dilewati
            ],
            'estimated_cost' => 250000,
        ]);
    }

    public function test_order_materials_reserves_and_validates_availability(): void
    {
        $this->seedInventory();

        $result = app(InstallationService::class)->orderMaterials($this->prospect, [], 42);

        $this->assertEquals('reserved', $result['mode']);
        $this->assertTrue($result['stock_reserved']);
        $this->assertFalse($result['stock_issued']);
        $this->assertFalse($result['accounting_posted']);
        $this->assertCount(2, $result['items'], 'materi tak terselesaikan harus gugur senyap');
        $this->assertEquals(250000, (float) $result['total_cost'], '1×150k + 4×25k');

        // Idempoten: order berikutnya memakai order aktif
        $again = app(InstallationService::class)->orderMaterials($this->prospect->fresh(), [], 42);
        $this->assertEquals($result['order_id'], $again['order_id']);
        $this->assertTrue($again['already_reserved']);

        // Reservasi tidak mengubah stok fisik
        $this->assertEquals(10, (float) MaterialStock::where('material_id', $this->meterId)->value('current_stock'));
    }

    public function test_reservation_rejects_insufficient_stock(): void
    {
        $this->seedInventory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stok tidak cukup');

        app(InstallationService::class)->orderMaterials($this->prospect, [
            ['material_code' => 'MTL-MTR-05', 'qty' => 99],
        ]);
    }

    public function test_complete_installs_stock_out_and_capitalization_journal(): void
    {
        $this->seedInventory();
        $installer = app(InstallationService::class);

        $orderResult = $installer->orderMaterials($this->prospect, [], 42);
        $schedule = $installer->schedule($this->prospect->fresh(), now()->toDateString(), 9);

        $installer->complete($schedule, [], 42);

        $order = InstallationMaterialOrder::findOrFail($orderResult['order_id']);
        $this->assertEquals('issued', $order->status);
        $this->assertNotNull($order->issued_at);
        $this->assertNotNull($order->journal_entry_id);

        // Stok fisik berkurang + mutasi tercatat reference installation
        $this->assertEquals(9, (float) MaterialStock::where('material_id', $this->meterId)->value('current_stock'));
        $this->assertEquals(46, (float) MaterialStock::where('material_id', $this->pipeId)->value('current_stock'));
        $this->assertEquals(2, MaterialTransaction::where('reference_type', 'installation')->where('transaction_type', 'stock_out')->count());

        // Jurnal double-entry: DEBIT 1-004 == KREDIT 1-003 == 250000
        $entry = JournalEntry::with('lines')->find($order->journal_entry_id);
        $this->assertNotNull($entry);
        $this->assertEquals(250000.0, (float) $entry->lines->where('type', 'DEBIT')->sum('amount'));
        $this->assertEquals(
            (float) $entry->lines->where('type', 'KREDIT')->sum('amount'),
            (float) $entry->lines->where('type', 'DEBIT')->sum('amount'),
        );
        $this->assertEquals('installation_material_order', $entry->reference_type);
    }

    public function test_cancel_reserved_order_then_reable_to_reserve(): void
    {
        $this->seedInventory();
        $installer = app(InstallationService::class);
        $result = $installer->orderMaterials($this->prospect, [], 42);
        $order = InstallationMaterialOrder::find($result['order_id']);

        $cancelled = $installer->cancelMaterials($order);
        $this->assertEquals('cancelled', $cancelled->status);

        // Setelah cancel, reservation baru dapat dibuat kembali
        $fresh = $installer->orderMaterials($this->prospect->fresh(), [], 42);
        $this->assertEquals('reserved', $fresh['mode']);
        $this->assertNotEquals($result['order_id'], $fresh['order_id']);
    }

    public function test_issued_order_cannot_be_issued_twice(): void
    {
        $this->seedInventory();
        $installer = app(InstallationService::class);
        $result = $installer->orderMaterials($this->prospect, [], 42);
        $order = InstallationMaterialOrder::find($result['order_id']);
        $installer->issueMaterials($order, 42);

        $this->expectException(RuntimeException::class);
        $installer->issueMaterials($order->fresh(), 42);
    }
}
