<?php

namespace Database\Seeders;

use App\Models\Complaint;
use App\Models\Customer;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\MeterRoute;
use App\Models\PdamOrganization;
use App\Models\ReadingPeriod;
use App\Models\SubscriptionModule;
use App\Models\Supplier;
use App\Models\TariffCategory;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\BillingService;
use App\Services\JournalService;
use App\Services\PaymentService;
use App\Services\StockService;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;

/**
 * OperationalDataSeeder — data operasional lengkap & terintegrasi untuk 2 tenant demo.
 *
 *  - PDAM Canada  (kode: pdam-canada)  → memakai data PDAM Pontianak. 27 modul LENGKAP.
 *  - PDAM Brazil  (kode: pdam-brazil)  → memakai data PDAM Surabaya. Hanya SEBAGIAN modul.
 *
 * "Canada/Brazil" hanya penamaan agar tidak terkena copyright; isinya data Indonesia.
 *
 * INTEGRASI: semua transaksi keuangan digerakkan lewat service asli
 * (JournalService, BillingService, PaymentService, StockService) sehingga
 * jurnal double-entry selalu BALANCE dan neraca konsisten:
 *
 *   1. Setoran modal awal      : DEBIT Kas (1-001)              | KREDIT Modal (3-001)
 *   2. Pembelian material      : DEBIT Persediaan (1-003)       | KREDIT Kas (1-001)
 *   3. Generate tagihan        : DEBIT Piutang (1-002)          | KREDIT Pendapatan Air (4-001)
 *   4. Pembayaran tagihan      : DEBIT Kas (1-001)              | KREDIT Piutang (1-002)
 *
 * Rantai: Pelanggan → Baca Meter → Tagihan → Pembayaran → Jurnal → Neraca,
 * plus Gudang → Stok → Jurnal Persediaan. Semua saling terhubung.
 *
 * Idempotent: aman dijalankan ulang (dilewati bila tenant sudah punya pelanggan).
 */
class OperationalDataSeeder extends Seeder
{
    private JournalService $journal;

    private BillingService $billing;

    private PaymentService $payments;

    private StockService $stock;

    public function run(): void
    {
        $this->journal = app(JournalService::class);
        $this->billing = app(BillingService::class);
        $this->payments = app(PaymentService::class);
        $this->stock = app(StockService::class);

        $canada = PdamOrganization::where('code', 'pdam-canada')->first();
        $brazil = PdamOrganization::where('code', 'pdam-brazil')->first();

        if ($canada) {
            $this->seedTenant($canada, $this->pontianakConfig(), full: true);
        }
        if ($brazil) {
            $this->seedTenant($brazil, $this->surabayaConfig(), full: false);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    //  KONFIGURASI DATA PER KOTA
    // ─────────────────────────────────────────────────────────────────────

    /** Data PDAM Pontianak (untuk PDAM Canada). */
    private function pontianakConfig(): array
    {
        return [
            'capital' => 5_000_000_000, // modal awal Rp 5 M
            'zones' => [
                ['ZN-01', 'Pontianak Kota', true],
                ['ZN-02', 'Pontianak Utara', false],
                ['ZN-03', 'Pontianak Timur', false],
                ['ZN-04', 'Pontianak Selatan', false],
            ],
            'suppliers' => [
                ['SUP-01', 'CV Khatulistiwa Pipa', '0561-733001', 'Jl. Gajah Mada No. 12, Pontianak'],
                ['SUP-02', 'PT Meter Kalbar Sejahtera', '0561-733002', 'Jl. Ahmad Yani No. 88, Pontianak'],
            ],
            'materials' => [
                ['MTL-PIPA-3', 'Pipa PVC 3 inci', 'pipa', 'batang', 85000, 500, 50],
                ['MTL-PIPA-4', 'Pipa PVC 4 inci', 'pipa', 'batang', 120000, 300, 40],
                ['MTL-MTR-05', 'Water Meter 1/2 inci', 'meter', 'unit', 175000, 400, 60],
                ['MTL-VLV-05', 'Gate Valve 1/2 inci', 'aksesoris', 'unit', 45000, 250, 40],
                ['MTL-CLM-05', 'Clamp Saddle 1/2 inci', 'aksesoris', 'unit', 22000, 350, 50],
            ],
            'customers' => [
                // [nama, tarif_code, zona_idx, telp, base_usage_m3]
                ['Budi Santoso', '2A2', 0, '081256700001', 18],
                ['Siti Aminah', '2A1', 0, '081256700002', 12],
                ['Hendra Wijaya', '2A3', 1, '081256700003', 25],
                ['Toko Sembako Berkah', '3A', 1, '081256700004', 40],
                ['CV Maju Jaya', '3B', 2, '081256700005', 65],
                ['Rina Marlina', '2A2', 2, '081256700006', 15],
                ['Kantor Kelurahan Sungai Jawi', '2F', 3, '081256700007', 30],
                ['PT Industri Karet Kalbar', '4B', 3, '081256700008', 120],
                ['Ahmad Fauzi', '2A1', 0, '081256700009', 10],
                ['Warung Kopi Asiang', '3A', 1, '081256700010', 22],
            ],
            'periods' => ['2026-04', '2026-05', '2026-06'],
        ];
    }

    /** Data PDAM Surabaya (untuk PDAM Brazil) — lebih ringkas. */
    private function surabayaConfig(): array
    {
        return [
            'capital' => 3_000_000_000, // modal awal Rp 3 M
            'zones' => [
                ['ZN-01', 'Surabaya Pusat', true],
                ['ZN-02', 'Surabaya Timur', false],
            ],
            'suppliers' => [
                ['SUP-01', 'PT Surya Pipa Nusantara', '031-5011001', 'Jl. Basuki Rahmat No. 5, Surabaya'],
            ],
            'materials' => [
                ['MTL-PIPA-3', 'Pipa PVC 3 inci', 'pipa', 'batang', 88000, 300, 40],
                ['MTL-MTR-05', 'Water Meter 1/2 inci', 'meter', 'unit', 180000, 250, 50],
                ['MTL-VLV-05', 'Gate Valve 1/2 inci', 'aksesoris', 'unit', 47000, 150, 30],
            ],
            'customers' => [
                ['Slamet Riyadi', '2A2', 0, '081345600001', 20],
                ['Dewi Kartika', '2A1', 0, '081345600002', 13],
                ['Toko Elektronik Jaya', '3A', 1, '081345600003', 38],
                ['Bambang Sutrisno', '2A3', 1, '081345600004', 28],
                ['CV Sinar Timur', '3B', 1, '081345600005', 55],
            ],
            'periods' => ['2026-05', '2026-06'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  ORKESTRASI PER TENANT
    // ─────────────────────────────────────────────────────────────────────

    private function seedTenant(PdamOrganization $org, array $cfg, bool $full): void
    {
        TenantContext::set($org->id);

        // Idempotent: bila sudah ada pelanggan, anggap sudah di-seed.
        if (Customer::query()->exists()) {
            TenantContext::clear();

            return;
        }

        $this->setModuleEntitlements($org->id, $full);

        $zones = $this->seedZones($cfg['zones']);
        $warehouses = $this->seedWarehouses($zones);
        $mainWarehouse = $warehouses[array_key_first($warehouses)];

        // 1) Modal awal → Kas + Ekuitas (dasar neraca)
        $this->seedCapital($cfg['capital']);

        // 2) Gudang: material, supplier, pembelian → stok + jurnal persediaan
        $this->seedSuppliers($cfg['suppliers']);
        $materials = $this->seedMaterials($cfg['materials']);
        $this->seedStockPurchase($materials, $mainWarehouse, $cfg['materials']);

        // 3) Rute baca meter per zona
        $routes = $this->seedRoutes($zones);

        // 4) Periode baca meter (ditutup) — prasyarat generate tagihan
        $this->seedReadingPeriods($cfg['periods']);

        // 5) Pelanggan + meter fisik + baca meter + tagihan + pembayaran
        $this->seedCustomersAndBilling($cfg, $zones, $routes);

        // 6) Pengaduan (CRM) — hanya bila modul relevan (Canada full; Brazil punya CRM)
        $this->seedComplaints($org->id);

        TenantContext::clear();
    }

    // ─────────────────────────────────────────────────────────────────────
    //  MODUL / ENTITLEMENT
    // ─────────────────────────────────────────────────────────────────────

    /** Modul yang aktif untuk tenant "partial" (Brazil). Sisanya tetap locked. */
    private const BRAZIL_ACTIVE = ['CORE', 'ZONE', 'WH', 'MTR', 'SRV', 'FIN+', 'CRM', 'BILL+', 'C360', 'APP'];

    private function setModuleEntitlements(int $orgId, bool $full): void
    {
        $subs = SubscriptionModule::withoutGlobalScopes()
            ->where('pdam_org_id', $orgId)
            ->get();

        foreach ($subs as $sub) {
            $shouldActivate = $full || in_array($sub->module_code, self::BRAZIL_ACTIVE, true);

            if ($shouldActivate && $sub->status !== 'active') {
                $sub->update([
                    'status' => 'active',
                    'activation_method' => $sub->activation_method ?? 'seeded_demo',
                    'activated_at' => $sub->activated_at ?? now(),
                ]);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    //  MASTER: ZONA, GUDANG, RUTE
    // ─────────────────────────────────────────────────────────────────────

    /** @return array<int,Zone> */
    private function seedZones(array $rows): array
    {
        $zones = [];
        foreach ($rows as [$code, $name, $isMain]) {
            $zones[] = Zone::create([
                'pdam_org_id' => TenantContext::id(),
                'code' => $code,
                'name' => $name,
                'office_address' => 'Kantor '.$name,
                'is_main' => $isMain,
                'is_active' => true,
            ]);
        }

        return $zones;
    }

    /** @return array<int,Warehouse> */
    private function seedWarehouses(array $zones): array
    {
        $warehouses = [];
        foreach ($zones as $i => $zone) {
            $warehouses[] = Warehouse::create([
                'pdam_org_id' => TenantContext::id(),
                'zone_id' => $zone->id,
                'code' => 'WH-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'name' => 'Gudang '.$zone->name,
                'warehouse_type' => $i === 0 ? 'main' : 'buffer',
                'address' => 'Gudang '.$zone->name,
                'is_active' => true,
            ]);
        }

        return $warehouses;
    }

    /** @return array<int,MeterRoute> */
    private function seedRoutes(array $zones): array
    {
        $routes = [];
        foreach ($zones as $i => $zone) {
            $routes[$zone->id] = MeterRoute::create([
                'pdam_org_id' => TenantContext::id(),
                'zone_id' => $zone->id,
                'code' => 'RT-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'name' => 'Rute '.$zone->name,
                'is_active' => true,
            ]);
        }

        return $routes;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  KEUANGAN: MODAL & PEMBELIAN MATERIAL
    // ─────────────────────────────────────────────────────────────────────

    private function seedCapital(float $amount): void
    {
        $this->journal->record(
            'Setoran modal awal pendirian PDAM',
            [
                ['account_code' => '1-001', 'type' => 'DEBIT', 'amount' => $amount, 'memo' => 'Kas awal'],
                ['account_code' => '3-001', 'type' => 'KREDIT', 'amount' => $amount, 'memo' => 'Modal disetor'],
            ],
            'CAPITAL',
            null,
            '2026-01-01',
        );
    }

    private function seedSuppliers(array $rows): void
    {
        foreach ($rows as [$code, $name, $phone, $address]) {
            Supplier::create([
                'pdam_org_id' => TenantContext::id(),
                'code' => $code,
                'name' => $name,
                'phone' => $phone,
                'address' => $address,
                'is_active' => true,
            ]);
        }
    }

    /** @return array<string,Material> keyed by material code */
    private function seedMaterials(array $rows): array
    {
        $materials = [];
        foreach ($rows as [$code, $name, $category, $unit, $price]) {
            $materials[$code] = Material::create([
                'pdam_org_id' => TenantContext::id(),
                'code' => $code,
                'name' => $name,
                'category' => $category,
                'unit' => $unit,
                'last_price' => $price,
                'is_active' => true,
            ]);
        }

        return $materials;
    }

    /**
     * Pembelian material tunai → stok masuk ke gudang utama + jurnal persediaan.
     * DEBIT Persediaan Material (1-003) | KREDIT Kas (1-001)
     */
    private function seedStockPurchase(array $materials, Warehouse $warehouse, array $rows): void
    {
        $totalPurchase = 0.0;

        foreach ($rows as [$code, $name, $category, $unit, $price, $qty, $minStock]) {
            $material = $materials[$code];

            // Stok fisik masuk (atomik + audit trail) via StockService
            $this->stock->stockIn(
                materialId: $material->id,
                warehouseId: $warehouse->id,
                qty: $qty,
                refType: 'initial_purchase',
                refId: null,
                userId: null,
            );

            // Set minimum stock untuk cek reorder
            MaterialStock::where('material_id', $material->id)
                ->where('warehouse_id', $warehouse->id)
                ->update(['minimum_stock' => $minStock]);

            $totalPurchase += $qty * $price;
        }

        // Jurnal pembelian material (material = ASET, bukan biaya)
        $this->journal->record(
            'Pembelian material persediaan awal (tunai)',
            [
                ['account_code' => '1-003', 'type' => 'DEBIT', 'amount' => $totalPurchase, 'memo' => 'Persediaan material'],
                ['account_code' => '1-001', 'type' => 'KREDIT', 'amount' => $totalPurchase, 'memo' => 'Kas keluar'],
            ],
            'STOCK_PURCHASE',
            null,
            '2026-02-01',
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    //  BACA METER: PERIODE
    // ─────────────────────────────────────────────────────────────────────

    private function seedReadingPeriods(array $periods): void
    {
        foreach ($periods as $period) {
            ReadingPeriod::create([
                'pdam_org_id' => TenantContext::id(),
                'period' => $period,
                'status' => 'closed', // ditutup → boleh generate tagihan
                'opened_at' => $period.'-01 08:00:00',
                'closed_at' => $period.'-25 17:00:00',
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    //  PELANGGAN → METER → BACA → TAGIHAN → PEMBAYARAN
    // ─────────────────────────────────────────────────────────────────────

    private function seedCustomersAndBilling(array $cfg, array $zones, array $routes): void
    {
        $orgId = TenantContext::id();
        $periods = $cfg['periods'];

        foreach ($cfg['customers'] as $idx => [$name, $tariffCode, $zoneIdx, $phone, $baseUsage]) {
            $zone = $zones[$zoneIdx];
            $tariff = TariffCategory::where('code', $tariffCode)->first();
            if (! $tariff) {
                continue; // tarif tidak ditemukan (jaga-jaga)
            }

            $custNumber = $this->buildCustomerNumber($idx + 1);
            $serial = 'MTR-'.str_pad((string) ($idx + 1), 5, '0', STR_PAD_LEFT);
            $installDate = '2025-12-15';

            $customer = Customer::create([
                'pdam_org_id' => $orgId,
                'customer_number' => $custNumber,
                'full_name' => $name,
                'phone' => $phone,
                'zone_id' => $zone->id,
                'tariff_category_id' => $tariff->id,
                'meter_route_id' => $routes[$zone->id]->id ?? null,
                'meter_serial_number' => $serial,
                'installation_date' => $installDate,
                'initial_reading' => 0,
                'status' => 'active',
            ]);

            // Meter fisik terpasang
            $meter = Meter::create([
                'pdam_org_id' => $orgId,
                'serial_number' => $serial,
                'brand' => 'Amico',
                'model' => 'B-Meter 1/2"',
                'diameter' => '0.5',
                'install_year' => 2025,
                'install_date' => $installDate,
                'condition' => 'baik',
                'status' => 'terpasang',
                'tamper_status' => 'normal',

                'customer_id' => $customer->id,
            ]);

            // Rangkaian pembacaan bertingkat + tagihan + sebagian pembayaran
            $previousReading = 0;
            foreach ($periods as $pIdx => $period) {
                // Variasikan pemakaian tiap bulan
                $usage = max(1, $baseUsage + (($idx + $pIdx) % 5) - 2);
                $currentReading = $previousReading + $usage;

                $prevRec = MeterReading::create([
                    'pdam_org_id' => $orgId,
                    'customer_id' => $customer->id,
                    'period' => $period,
                    'reading_value' => $currentReading,
                    'reading_date' => $period.'-20',
                    'reading_type' => 'actual',
                    'read_by' => null,
                    'verified_at' => $period.'-22 10:00:00',
                ]);

                // Generate tagihan lewat BillingService (auto jurnal Piutang↔Pendapatan)
                try {
                    $bill = $this->billing->generateForCustomer(
                        $customer,
                        $period,
                        $previousReading,
                        $currentReading,
                        null,
                        $prevRec->id,
                    );
                } catch (\Throwable $e) {
                    // Lewati bila sudah ada / periode belum siap
                    $previousReading = $currentReading;

                    continue;
                }

                // Pola pembayaran: bulan lama dibayar, bulan terakhir sebagian nunggak
                $isLastPeriod = $pIdx === count($periods) - 1;
                $shouldPay = ! $isLastPeriod || ($idx % 3 !== 0);

                if ($shouldPay) {
                    $payment = $this->payments->createForBill($bill, 'cash', 'tunai');
                    $this->payments->markPaid(
                        $payment,
                        transactionId: 'CASH-SEED-'.$bill->id,
                        method: 'tunai',
                    );
                }

                $previousReading = $currentReading;
            }
        }
    }

    private function buildCustomerNumber(int $seq): string
    {
        return sprintf('%s-%05d', TenantContext::id(), $seq);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  CRM: PENGADUAN CONTOH
    // ─────────────────────────────────────────────────────────────────────

    private function seedComplaints(int $orgId): void
    {
        $customers = Customer::query()->limit(3)->get();
        if ($customers->isEmpty()) {
            return;
        }

        $samples = [
            ['kebocoran', 'high', 'Pipa bocor di depan rumah', 'Air merembes dari sambungan pipa depan rumah.'],
            ['tagihan', 'medium', 'Tagihan bulan ini terlalu tinggi', 'Mohon dicek, tagihan naik drastis padahal pemakaian normal.'],
            ['air_mati', 'high', 'Air tidak mengalir sejak 2 hari', 'Sudah 2 hari air tidak keluar sama sekali di wilayah kami.'],
        ];

        foreach ($customers as $i => $customer) {
            [$category, $priority, $subject, $desc] = $samples[$i % count($samples)];

            Complaint::create([
                'pdam_org_id' => $orgId,
                'customer_id' => $customer->id,
                'ticket_number' => sprintf('TKT-%d-%04d', $orgId, $i + 1),
                'category' => $category,
                'priority' => $priority,
                'subject' => $subject,
                'description' => $desc,
                'status' => $i === 0 ? 'open' : ($i === 1 ? 'in_progress' : 'resolved'),
                'sla_due_at' => now()->addDays(2),
                'resolved_at' => $i === 2 ? now()->subDay() : null,
                'resolution' => $i === 2 ? 'Sudah diperbaiki teknisi, air kembali normal.' : null,
            ]);
        }
    }
}
