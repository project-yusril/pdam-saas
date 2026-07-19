<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use App\Services\JournalService;
use App\Services\StockService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * PurchaseOrderController — pengadaan multi-level approval (PRD 3.3, Fase 5).
 * Alur: draft → tech_approved → dir_approved → fin_approved → purchased → received.
 * Saat purchased: jurnal Persediaan(DEBIT) ↔ Kas/Utang(KREDIT).
 * Saat received: material masuk Gudang Utama via StockService.
 */
class PurchaseOrderController extends Controller
{
    public function __construct(
        private StockService $stock,
        private JournalService $journal,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);
        $query = PurchaseOrder::orderByDesc('created_at');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return ApiResponse::paginated($query->paginate($perPage));
    }

    /** Kepala Gudang: buat PO (draft). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'integer'],
            'urgency' => ['nullable', 'in:normal,urgent'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $total = collect($data['items'])->sum(fn ($i) => $i['qty'] * $i['price']);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-'.strtoupper(Str::random(8)),
            'supplier_id' => $data['supplier_id'] ?? null,
            'requested_by' => $request->user()->id,
            'items' => $data['items'],
            'total_estimated_price' => $total,
            'urgency' => $data['urgency'] ?? 'normal',
            'status' => 'draft',
        ]);

        return ApiResponse::message('PO dibuat.', $po, 201);
    }

    /** Approval berjenjang: tech → dir → fin (sesuai role/permission). */
    public function approve(Request $request, PurchaseOrder $po): JsonResponse
    {
        $data = $request->validate([
            'level' => ['required', 'in:tech,dir,fin'],
        ]);

        $expected = ['tech' => 'draft', 'dir' => 'tech_approved', 'fin' => 'dir_approved'];
        if ($po->status !== $expected[$data['level']]) {
            return ApiResponse::error('INVALID_STATE', "PO tidak siap untuk approval {$data['level']}.", null, 422);
        }

        $next = ['tech' => 'tech_approved', 'dir' => 'dir_approved', 'fin' => 'fin_approved'];
        $po->update([
            "{$data['level']}_approved_by" => $request->user()->id,
            "{$data['level']}_approved_at" => now(),
            'status' => $next[$data['level']],
        ]);

        return ApiResponse::message('PO disetujui.', $po->fresh());
    }

    /** Staf Keuangan: eksekusi dana → jurnal Persediaan ↔ Kas. */
    public function purchase(Request $request, PurchaseOrder $po): JsonResponse
    {
        if ($po->status !== 'fin_approved') {
            return ApiResponse::error('INVALID_STATE', 'PO belum disetujui keuangan.', null, 422);
        }

        $entry = $this->journal->record(
            "Pengadaan material {$po->po_number}",
            [
                ['account_code' => '1-003', 'type' => 'DEBIT', 'amount' => $po->total_estimated_price, 'memo' => 'Persediaan material'],
                ['account_code' => '1-001', 'type' => 'KREDIT', 'amount' => $po->total_estimated_price, 'memo' => 'Pembayaran supplier'],
            ],
            'PURCHASE_ORDER',
            $po->id,
        );

        $po->update(['status' => 'purchased', 'purchased_at' => now(), 'journal_entry_id' => $entry->id]);

        return ApiResponse::message('Dana dieksekusi, PO purchased.', $po->fresh());
    }

    /** Kepala Gudang: terima material → masuk Gudang Utama. */
    public function receive(Request $request, PurchaseOrder $po): JsonResponse
    {
        if ($po->status !== 'purchased') {
            return ApiResponse::error('INVALID_STATE', 'PO belum dalam status purchased.', null, 422);
        }

        $mainWarehouse = Warehouse::where('warehouse_type', 'main')->firstOrFail();

        foreach ($po->items as $item) {
            $this->stock->stockIn(
                (int) $item['material_id'],
                $mainWarehouse->id,
                (float) $item['qty'],
                'purchase_order',
                $po->id,
                $request->user()->id,
            );
            // Perbarui harga terakhir material (untuk estimasi berikutnya)
            Material::where('id', $item['material_id'])->update(['last_price' => $item['price']]);
        }

        $po->update(['status' => 'received', 'received_at' => now()]);

        return ApiResponse::message('Material diterima di Gudang Utama.', $po->fresh());
    }
}
