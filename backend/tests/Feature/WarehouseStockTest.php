<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\PdamOrganization;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\StockService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifikasi mutasi stok gudang (PRD 3.3, Fase 5):
 * - Stok tak pernah minus (stockOut melebihi saldo ditolak).
 * - Transfer memindahkan stok antar gudang; quantity_received < requested
 *   hanya menambah sejumlah yang diterima (selisih kerusakan hilang dari sistem
 *   tapi tetap keluar dari asal → tercatat di material_transactions).
 * - Setiap mutasi mencatat material_transactions (audit).
 */
class WarehouseStockTest extends TestCase
{
    use RefreshDatabase;

    private int $materialId;

    private int $mainWarehouseId;

    private int $bufferWarehouseId;

    protected function setUp(): void
    {
        parent::setUp();
        (new PdamOrganization)->forceFill(['id' => 1, 'code' => 'test', 'name' => 'Test PDAM'])->save();
        TenantContext::set(1);

        // COA minimal untuk konteks (tidak dipakai StockService langsung,
        // tapi memastikan tenant konsisten).
        ChartOfAccount::create(['pdam_org_id' => 1, 'code' => '1-003', 'name' => 'Persediaan', 'type' => 'ASSET', 'normal_balance' => 'DEBIT']);

        // Zone + gudang (FK material_stocks.warehouse_id → warehouses.id)
        $mainZone = Zone::create(['pdam_org_id' => 1, 'code' => 'W0', 'name' => 'Kantor Utama', 'is_main' => true]);
        $bufferZone = Zone::create(['pdam_org_id' => 1, 'code' => 'W1', 'name' => 'Wilayah I']);

        $this->mainWarehouseId = Warehouse::create([
            'pdam_org_id' => 1, 'zone_id' => $mainZone->id, 'code' => 'GU', 'name' => 'Gudang Utama', 'warehouse_type' => 'main',
        ])->id;
        $this->bufferWarehouseId = Warehouse::create([
            'pdam_org_id' => 1, 'zone_id' => $bufferZone->id, 'code' => 'GB1', 'name' => 'Gudang Buffer I', 'warehouse_type' => 'buffer',
        ])->id;

        $this->materialId = Material::create([
            'pdam_org_id' => 1,
            'code' => 'PVC-075',
            'name' => 'Pipa PVC 3/4"',
            'category' => 'pipa',
            'unit' => 'meter',
        ])->id;
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_stock_in_then_out_updates_balance_and_logs(): void
    {
        $svc = app(StockService::class);

        $svc->stockIn($this->materialId, $this->mainWarehouseId, qty: 200, refType: 'purchase_order', refId: 1);
        $svc->stockOut($this->materialId, $this->mainWarehouseId, qty: 50, refType: 'installation', refId: 1);

        $stock = MaterialStock::where('material_id', $this->materialId)->where('warehouse_id', $this->mainWarehouseId)->first();
        $this->assertEquals(150, (float) $stock->current_stock);

        // 2 transaksi tercatat (in + out)
        $this->assertDatabaseCount('material_transactions', 2);

    }

    public function test_stock_out_beyond_balance_is_rejected(): void
    {
        $svc = app(StockService::class);
        $svc->stockIn($this->materialId, $this->mainWarehouseId, qty: 10, refType: 'purchase_order', refId: 1);

        $this->expectException(RuntimeException::class);
        $svc->stockOut($this->materialId, $this->mainWarehouseId, qty: 20, refType: 'installation', refId: 1);
    }

    public function test_transfer_with_partial_receive(): void
    {
        $svc = app(StockService::class);
        // Gudang utama punya 200, buffer kosong
        $svc->stockIn($this->materialId, $this->mainWarehouseId, qty: 200, refType: 'purchase_order', refId: 1);

        // Kirim 50, tapi hanya 45 yang diterima (5 rusak di jalan)
        $svc->transfer($this->materialId, $this->mainWarehouseId, $this->bufferWarehouseId, qtyRequested: 50, qtyReceived: 45, refId: 99);

        $main = MaterialStock::where('material_id', $this->materialId)->where('warehouse_id', $this->mainWarehouseId)->first();
        $buffer = MaterialStock::where('material_id', $this->materialId)->where('warehouse_id', $this->bufferWarehouseId)->first();

        $this->assertEquals(150, (float) $main->current_stock);   // 200 - 50 keluar
        $this->assertEquals(45, (float) $buffer->current_stock);  // hanya 45 diterima
    }
}
