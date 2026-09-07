<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerProspect;
use App\Models\InstallationMaterialOrder;
use App\Models\InstallationSchedule;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\SubscriptionModule;
use App\Models\Warehouse;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * InstallationService — penjadwalan, pelaksanaan, material, dan aktivasi
 * pemasangan baru (PRD 3.1 tahap 6):
 * payment_paid → order/reservasi material → installation_scheduled →
 * (selesai) stock-out + jurnal kapitalisasi → installed → active.
 *
 * Material: dengan modul WH aktif + gudang utama + stok cukup, order dibuat
 * `reserved`. Saat pemasangan selesai, stok di-stock-out dan jurnal dibuat
 * DEBIT aset jaringan (kapitalisasi, rekomendasi PRD 23) KREDIT persediaan.
 */
class InstallationService
{
    public function __construct(
        private StockService $stock,
        private JournalService $journal
    ) {}

    /**
     * Order/reservasi material pemasangan untuk prospek.
     *
     * @param  array<int, array{material_id?:int, material_code?:string, qty?:float|int, quantity?:float|int}>  $overrides
     * @return array mode = planning_only | reserved
     */
    public function orderMaterials(CustomerProspect $prospect, array $overrides = [], ?int $userId = null): array
    {
        if (! in_array($prospect->status, ['payment_paid', 'installation_scheduled'], true)) {
            throw new RuntimeException('Prospek belum siap untuk order material (harus sudah bayar).');
        }

        // Cek entitlement modul WH untuk tenant ini (tanpa hard-dependency) —
        // dilakukan di luar transaksi agar jalur planning_only tidak menahan baris.
        $materials = $overrides !== []
            ? $overrides
            : ($prospect->survey?->estimated_materials ?? []);

        $whActive = SubscriptionModule::query()
            ->where('pdam_org_id', $prospect->pdam_org_id)
            ->where('module_code', 'WH')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        if (! $whActive) {
            return [
                'mode' => 'planning_only',
                'reason' => 'wh_inactive',
                'warehouse_module_active' => false,
                'stock_reserved' => false,
                'stock_issued' => false,
                'accounting_posted' => false,
                'materials' => $materials,
            ];
        }

        $items = $this->resolveItems($prospect->pdam_org_id, $materials);

        if ($items === []) {
            return [
                'mode' => 'planning_only',
                'reason' => 'no_resolvable_materials',
                'warehouse_module_active' => true,
                'stock_reserved' => false,
                'stock_issued' => false,
                'accounting_posted' => false,
                'materials' => $materials,
            ];
        }

        $warehouse = Warehouse::where('pdam_org_id', $prospect->pdam_org_id)
            ->where('warehouse_type', 'main')
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $warehouse) {
            return [
                'mode' => 'planning_only',
                'reason' => 'no_main_warehouse',
                'warehouse_module_active' => true,
                'stock_reserved' => false,
                'stock_issued' => false,
                'accounting_posted' => false,
                'materials' => $items,
            ];
        }

        return DB::transaction(function () use ($prospect, $warehouse, $items, $userId) {
            // Serialize check-then-insert per prospek; double-click order tidak bisa
            // membuat dua reserved order + dua stock-out/jurnal (review race #5).
            $locked = CustomerProspect::whereKey($prospect->id)->lockForUpdate()->first();
            if (! $locked) {
                throw new RuntimeException('Prospek tidak ditemukan.');
            }

            $existing = InstallationMaterialOrder::where('prospect_id', $locked->id)
                ->whereIn('status', [
                    InstallationMaterialOrder::STATUS_RESERVED,
                    InstallationMaterialOrder::STATUS_ISSUED,
                ])
                ->latest('id')
                ->first();

            if ($existing) {
                return $this->reservedPayload($existing, $locked, already: true);
            }

            // Validasi stok dari TOTAL qty per material (duplikat material di survey
            // atau override API harus dijumlah, bukan dicek baris-per-baris).
            foreach ($items as $item) {
                $available = (float) (MaterialStock::where('material_id', $item['material_id'])
                    ->where('warehouse_id', $warehouse->id)
                    ->value('current_stock') ?? 0);
                if ($available < $item['qty']) {
                    throw new RuntimeException(
                        "Stok tidak cukup untuk {$item['name']}: tersedia {$available}, dibutuhkan {$item['qty']} {$item['unit']}."
                    );
                }
            }

            $total = round(array_sum(array_column($items, 'line_cost')), 2);

            $order = InstallationMaterialOrder::create([
                'pdam_org_id' => $locked->pdam_org_id,
                'prospect_id' => $locked->id,
                'warehouse_id' => $warehouse->id,
                'status' => InstallationMaterialOrder::STATUS_RESERVED,
                'items' => $items,
                'total_cost' => $total,
                'reserved_at' => now(),
                'created_by' => $userId,
            ]);

            return $this->reservedPayload($order, $locked, already: false);
        });
    }

    /** Keluarkan (stock-out) material order + posting jurnal kapitalisasi. */
    public function issueMaterials(InstallationMaterialOrder $order, ?int $userId = null): InstallationMaterialOrder
    {
        if ($order->status !== InstallationMaterialOrder::STATUS_RESERVED) {
            throw new RuntimeException('Material order bukan berstatus reserved — tidak dapat di-stock-out.');
        }

        return DB::transaction(function () use ($order, $userId) {
            foreach ($order->items as $item) {
                $this->stock->stockOut(
                    (int) $item['material_id'],
                    (int) $order->warehouse_id,
                    (float) $item['qty'],
                    'installation',
                    $order->id,
                    $userId,
                );
            }

            $entryId = null;
            if ((float) $order->total_cost > 0) {
                $debitCode = $this->resolveActiveAccount($order->pdam_org_id);
                $entry = $this->journal->record(
                    "Stock-out material pemasangan (prospek #{$order->prospect_id})",
                    [
                        ['account_code' => $debitCode, 'type' => 'DEBIT', 'amount' => (float) $order->total_cost],
                        ['account_code' => $this->inventoryAccountCode(), 'type' => 'KREDIT', 'amount' => (float) $order->total_cost],
                    ],
                    'installation_material_order',
                    $order->id,
                );
                $entryId = $entry->id;
            }

            $order->update([
                'status' => InstallationMaterialOrder::STATUS_ISSUED,
                'issued_at' => now(),
                'journal_entry_id' => $entryId,
            ]);

            return $order->fresh();
        });
    }

    /** Batalkan order yang belum di-stock-out. */
    public function cancelMaterials(InstallationMaterialOrder $order): InstallationMaterialOrder
    {
        if ($order->status !== InstallationMaterialOrder::STATUS_RESERVED) {
            throw new RuntimeException('Hanya order reserved yang dapat dibatalkan (issued bersifat final).');
        }

        $order->update(['status' => InstallationMaterialOrder::STATUS_CANCELLED]);

        return $order->fresh();
    }

    /** Jadwalkan pemasangan (prospek harus sudah bayar biaya pemasangan). */
    public function schedule(CustomerProspect $prospect, string $scheduledDate, int $technicianId): InstallationSchedule
    {
        if ($prospect->status !== 'payment_paid') {
            throw new RuntimeException('Prospek belum melunasi biaya pemasangan.');
        }

        return DB::transaction(function () use ($prospect, $scheduledDate, $technicianId) {
            $schedule = InstallationSchedule::create([
                'pdam_org_id' => $prospect->pdam_org_id,
                'prospect_id' => $prospect->id,
                'scheduled_date' => $scheduledDate,
                'technician_id' => $technicianId,
                'status' => 'scheduled',
            ]);

            $prospect->update(['status' => 'installation_scheduled']);

            return $schedule;
        });
    }

    /**
     * Teknisi menandai pemasangan selesai (dengan foto hasil).
     * Bila ada material order reserved → otomatis stock-out + jurnal.
     */
    public function complete(InstallationSchedule $schedule, array $resultPhotoUrls = [], ?int $userId = null): InstallationSchedule
    {
        if ($schedule->status !== 'scheduled') {
            throw new RuntimeException('Jadwal pemasangan tidak dalam status terjadwal.');
        }

        return DB::transaction(function () use ($schedule, $resultPhotoUrls, $userId) {
            $order = InstallationMaterialOrder::where('prospect_id', $schedule->prospect_id)
                ->where('status', InstallationMaterialOrder::STATUS_RESERVED)
                ->latest('id')
                ->first();

            if ($order) {
                $this->issueMaterials($order, $userId);
            }

            $schedule->update([
                'status' => 'installed',
                'result_photo_urls' => $resultPhotoUrls,
                'installed_at' => now(),
            ]);

            CustomerProspect::where('id', $schedule->prospect_id)
                ->update(['status' => 'installed']);

            return $schedule->fresh();
        });
    }

    /**
     * Aktivasi: buat record Customer aktif dari prospek yang sudah terpasang.
     * Menetapkan nomor pelanggan, meter awal 0 m³, dan menautkan rute (opsional).
     */
    public function activate(CustomerProspect $prospect, array $attributes = []): Customer
    {
        if ($prospect->status !== 'installed') {
            throw new RuntimeException('Prospek belum berstatus terpasang.');
        }

        return DB::transaction(function () use ($prospect, $attributes) {
            $customer = Customer::create([
                'pdam_org_id' => $prospect->pdam_org_id,
                'user_id' => $prospect->user_id,
                'prospect_id' => $prospect->id,
                'customer_number' => $attributes['customer_number'] ?? $this->generateCustomerNumber(),
                'full_name' => $prospect->full_name,
                'phone' => $prospect->phone,
                'email' => $prospect->email,
                'zone_id' => $prospect->zone_id,
                'tariff_category_id' => $attributes['tariff_category_id'] ?? $prospect->tariff_category_id,
                'meter_serial_number' => $attributes['meter_serial_number'] ?? null,
                'meter_route_id' => $attributes['meter_route_id'] ?? null,
                'installation_date' => now()->toDateString(),
                'initial_reading' => 0, // meter awal 0 m³ (PRD 3.1)
                'status' => 'active',
            ]);

            $prospect->update(['status' => 'active']);

            $customer->statusHistory()->create([
                'pdam_org_id' => $customer->pdam_org_id,
                'from_status' => null,
                'to_status' => 'active',
                'reason' => 'Aktivasi pelanggan baru',
            ]);

            return $customer;
        });
    }

    protected function generateCustomerNumber(): string
    {
        $org = TenantContext::id();

        return sprintf('CUST-%s-%s', $org, strtoupper(uniqid()));
    }

    /**
     * Normalisasi entri material ({material_id|material_code, qty|quantity})
     * menjadi item definitif dengan snapshot harga.
     *
     * Duplikat material_id DIJUMLAH qty (review: dua baris sama dengan stok 10/8
     * lolos cek per-baris lalu meledak di stock-out saat complete).
     *
     * @param  array<int, array<string, mixed>>  $materials
     * @return array<int, array<string, mixed>>
     */
    protected function resolveItems(int $orgId, array $materials): array
    {
        $byMaterial = [];

        foreach ($materials as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $qty = (float) ($raw['qty'] ?? $raw['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $material = null;
            $id = $raw['material_id'] ?? $raw['id'] ?? null;
            $code = $raw['material_code'] ?? $raw['code'] ?? null;
            if ($id) {
                $material = Material::where('id', (int) $id)->where('pdam_org_id', $orgId)->where('is_active', true)->first();
            } elseif ($code) {
                $material = Material::where('code', (string) $code)->where('pdam_org_id', $orgId)->where('is_active', true)->first();
            }

            if (! $material) {
                continue;
            }

            $unitCost = (float) $material->last_price;
            $key = (int) $material->id;
            $prev = $byMaterial[$key] ?? null;
            $newQty = ($prev['qty'] ?? 0) + $qty;
            $byMaterial[$key] = [
                'material_id' => $key,
                'material_code' => (string) $material->code,
                'name' => (string) $material->name,
                'unit' => (string) $material->unit,
                'qty' => $newQty,
                'unit_cost' => $unitCost,
                'line_cost' => round($newQty * $unitCost, 2),
            ];
        }

        return array_values($byMaterial);
    }

    protected function resolveActiveAccount(int $orgId): string
    {
        foreach ($this->capitalizationAccountCodes() as $code) {
            if (ChartOfAccount::where('code', $code)->where('pdam_org_id', $orgId)->where('is_active', true)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Akun COA kapitalisasi material belum tersedia untuk tenant ini — set config business.accounting.installation_capitalization_account / COA 1-004/5-001.');
    }

    /** @return array<int,string> urutan akun debit (override config lalu fallback) */
    protected function capitalizationAccountCodes(): array
    {
        $primary = (string) config('business.accounting.installation_capitalization_account', '1-004');

        return array_values(array_unique(array_filter([$primary, '1-004', '5-001'])));
    }

    protected function inventoryAccountCode(): string
    {
        return (string) config('business.accounting.inventory_account', '1-003');
    }

    protected function reservedPayload(InstallationMaterialOrder $order, CustomerProspect $prospect, bool $already): array
    {
        return [
            'mode' => 'reserved',
            'already_reserved' => $already,
            'order_id' => $order->id,
            'status' => $order->status,
            'warehouse_id' => $order->warehouse_id,
            'warehouse_module_active' => true,
            'stock_reserved' => $order->status === InstallationMaterialOrder::STATUS_RESERVED,
            'stock_issued' => $order->status === InstallationMaterialOrder::STATUS_ISSUED,
            'accounting_posted' => $order->journal_entry_id !== null,
            'journal_entry_id' => $order->journal_entry_id,
            'total_cost' => (float) $order->total_cost,
            'items' => $order->items,
            'materials' => $order->items,
        ];
    }
}
