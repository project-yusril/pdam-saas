<?php

namespace Database\Seeders;

use App\Models\BillingSetting;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\Meter;
use App\Models\MeterRoute;
use App\Models\MeterRouteAssignment;
use App\Models\PdamOrganization;
use App\Models\ReadingPeriod;
use App\Models\Role;
use App\Models\SubscriptionModule;
use App\Models\Supplier;
use App\Models\TariffCategory;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\JournalService;
use App\Services\StockService;
use App\Services\TenantProvisioningService;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * SambasTenantSeeder — tenant demo "PDAM Kabupaten Sambas" (27 modul LENGKAP).
 *
 * Membuktikan 27 modul saling terintegrasi. Data alamat (kecamatan/desa/jalan)
 * memakai data nyata Kabupaten Sambas, Kalimantan Barat (BPS 6101).
 *
 * Rantai inti: Route+petugas -> Pelanggan+Meter -> Baca Meter (foto+petugas)
 * -> Tagihan (golongan tarif bertingkat) -> Pembayaran -> Jurnal -> Neraca.
 * Semua transaksi keuangan lewat service asli (Billing/Payment/Journal/Stock)
 * sehingga SUM(DEBIT)==SUM(KREDIT) dan neraca BALANCE.
 * Idempotent: dilewati bila tenant sudah punya pelanggan (kecuali force).
 */
class SambasTenantSeeder extends Seeder
{
    public const CODE = 'pdam-sambas';
    public const NAME = 'PDAM Kabupaten Sambas';
    public const CITY = 'Sambas';
    public const PROVINCE = 'Kalimantan Barat';
    public const DEFAULT_PASSWORD = '12345678';

    /** Jumlah pelanggan (bisa dioverride via env SEED_CUSTOMER_COUNT). */
    protected int $customerCount;

    private JournalService $journal;
    private StockService $stock;

    private const ACCOUNTS = [
        ['1-001', 'Kas / Bank', 'ASSET', 'DEBIT'],
        ['1-002', 'Piutang Pelanggan', 'ASSET', 'DEBIT'],
        ['1-003', 'Persediaan Material', 'ASSET', 'DEBIT'],
        ['1-004', 'Aset Jaringan / Instalasi', 'ASSET', 'DEBIT'],
        ['2-001', 'Utang Usaha', 'LIABILITY', 'KREDIT'],
        ['3-001', 'Modal / Ekuitas', 'EQUITY', 'KREDIT'],
        ['4-001', 'Pendapatan Air', 'REVENUE', 'KREDIT'],
        ['4-002', 'Pendapatan Pemasangan Baru', 'REVENUE', 'KREDIT'],
        ['4-003', 'Pendapatan Penyambungan Kembali', 'REVENUE', 'KREDIT'],
        ['4-004', 'Pendapatan Denda', 'REVENUE', 'KREDIT'],
        ['5-001', 'Beban Material Perbaikan', 'EXPENSE', 'DEBIT'],
        ['5-002', 'Beban Kerugian Persediaan', 'EXPENSE', 'DEBIT'],
        ['1-100', 'Tanah', 'ASSET', 'DEBIT'],
        ['1-110', 'Bangunan & Instalasi Pengolahan Air (IPA)', 'ASSET', 'DEBIT'],
        ['1-120', 'Mesin & Pompa', 'ASSET', 'DEBIT'],
        ['1-130', 'Jaringan Pipa Transmisi/Distribusi', 'ASSET', 'DEBIT'],
        ['1-140', 'Kendaraan', 'ASSET', 'DEBIT'],
        ['1-150', 'Inventaris Kantor', 'ASSET', 'DEBIT'],
        ['1-160', 'Meter Induk', 'ASSET', 'DEBIT'],
        ['1-180', 'Konstruksi Dalam Pengerjaan (CIP)', 'ASSET', 'DEBIT'],
        ['1-190', 'Akumulasi Penyusutan', 'ASSET', 'KREDIT'],
        ['4-005', 'Pendapatan Pelepasan Aset', 'REVENUE', 'KREDIT'],
        ['5-101', 'Beban Penyusutan', 'EXPENSE', 'DEBIT'],
        ['5-102', 'Rugi Pelepasan Aset', 'EXPENSE', 'DEBIT'],
    ];

    private const TARIFFS = [
        ['1A', 'Sosial Umum', 'sosial', [800, 1900, 1900]],
        ['1B', 'Sosial Khusus A', 'sosial', [1000, 2200, 2200]],
        ['1C', 'Sosial Khusus B', 'sosial', [1300, 2400, 2400]],
        ['2A1', 'Rumah Tangga Sederhana', 'rumah_tangga', [1800, 3400, 3400]],
        ['2A2', 'Rumah Tangga Semi Permanen', 'rumah_tangga', [2900, 5000, 5300]],
        ['2A3', 'Rumah Tangga Permanen', 'rumah_tangga', [3200, 5500, 6200]],
        ['2D', 'Rumah Tangga Daerah Perdagangan', 'rumah_tangga', [3500, 6000, 6500]],
        ['2B', 'Rumah Tangga Permanen Mandiri', 'rumah_tangga', [5000, 7400, 8200]],
        ['2F', 'Instansi Pemerintah', 'khusus', [6500, 8000, 8500]],
        ['3A', 'Niaga Kecil', 'niaga', [5200, 8000, 8600]],
        ['3B', 'Niaga Menengah', 'niaga', [6000, 8500, 9000]],
        ['3C', 'Niaga Besar', 'niaga', [6500, 10500, 11500]],
        ['4A', 'Industri Kecil', 'industri', [5500, 8700, 9200]],
        ['4B', 'Industri Menengah', 'industri', [6000, 9000, 10000]],
        ['4C', 'Industri Besar', 'industri', [6500, 11000, 12000]],
        ['5A', 'Pelabuhan', 'khusus', [40000, 50000, 60000]],
        ['5B', 'Mobil Tangki', 'khusus', [30000, 30000, 30000]],
    ];

    private const ZONES = [
        ['ZN-01', 'Sambas Kota', ['610101', '610110', '610115', '610114'], true],
        ['ZN-02', 'Pemangkat', ['610105', '610113', '610118'], false],
        ['ZN-03', 'Tebas', ['610104', '610112'], false],
        ['ZN-04', 'Jawai', ['610103', '610116'], false],
        ['ZN-05', 'Teluk Keramat', ['610102', '610111', '610117'], false],
        ['ZN-06', 'Selakau', ['610107', '610119'], false],
        ['ZN-07', 'Paloh', ['610108', '610109', '610106'], false],
    ];

    private const CHEMICALS = [
        ['TAWAS', 'Aluminium Sulfat (Tawas)', 'kg', 30.0000, 60.0000],
        ['PAC', 'Poly Aluminium Chloride', 'kg', 25.0000, 50.0000],
        ['KAPORIT', 'Kaporit (Kalsium Hipoklorit)', 'kg', 3.0000, 8.0000],
    ];

    /** 0=tepat waktu(70%),1=nunggak1bln(10%),2=nunggak2bln(10%),3=nunggak3bln(5%),4=putus(5%). */
    private function paymentBucket(int $i): int
    {
        $mod = $i % 100;
        if ($mod < 70) { return 0; }
        if ($mod < 80) { return 1; }
        if ($mod < 90) { return 2; }
        if ($mod < 95) { return 3; }

        return 4;
    }


    public function run(): void
    {
        $this->customerCount = max(1, (int) (env('SEED_CUSTOMER_COUNT', 3000)));

        $this->journal = app(JournalService::class);
        $this->stock = app(StockService::class);

        $org = $this->provision();

        $force = filter_var(env('SEED_SAMBAS_FORCE', false), FILTER_VALIDATE_BOOLEAN);
        $hasCustomers = Customer::withoutTenant()->where('pdam_org_id', $org->id)->exists();
        if ($hasCustomers && ! $force) {
            $this->command?->warn("[SAMBAS] Tenant {$org->code} sudah ter-seed. Lewati.");

            return;
        }

        TenantContext::set($org->id);

        $this->activateAllModules($org->id);
        $this->seedRoleUsers($org->id);
        $this->seedMasterFinance();
        $this->seedAddress();
        $zones = $this->seedZones();
        $warehouses = $this->seedWarehouses($zones);
        $this->seedSuppliers();
        $materials = $this->seedMaterials();
        $this->seedCapital();
        $this->seedInitialStock($materials, $warehouses);
        $routes = $this->seedRoutes($zones);
        $this->seedRouteStreets();
        $this->seedRouteAssignments($routes);
        $this->seedReadingPeriods();

        $this->seedCustomersAndBilling($zones, $routes, $warehouses);
        $this->seedDisconnections();

        $this->seedWarehouseOperations($warehouses, $materials);
        $this->seedProcurement();
        $this->seedEnterpriseModules($org->id, $zones, $warehouses);

        TenantContext::clear();

        $this->command?->info("[SAMBAS] Selesai: {$this->customerCount} pelanggan, ".count($zones)." zona, 27 modul aktif.");
    }

    private function provision(): PdamOrganization
    {
        $org = PdamOrganization::where('code', self::CODE)->first();
        if ($org) {
            return $org;
        }

        /** @var TenantProvisioningService $svc */
        $svc = app(TenantProvisioningService::class);

        return $svc->provision(
            orgData: ['code' => self::CODE, 'name' => self::NAME, 'city' => self::CITY, 'province' => self::PROVINCE],
            adminData: ['name' => 'Admin '.self::NAME, 'email' => 'admin_tenant@gmail.com', 'password' => self::DEFAULT_PASSWORD],
        );
    }

    private function activateAllModules(int $orgId): void
    {
        $subs = SubscriptionModule::withoutGlobalScopes()->where('pdam_org_id', $orgId)->get();
        foreach ($subs as $sub) {
            if ($sub->status !== 'active') {
                $sub->update([
                    'status' => 'active',
                    'activation_method' => $sub->activation_method ?? 'seeded_demo',
                    'activated_at' => $sub->activated_at ?? now(),
                ]);
            }
        }
    }

    private function seedRoleUsers(int $orgId): void
    {
        $roles = Role::withoutGlobalScopes()->where('pdam_org_id', $orgId)->get()->keyBy('code');

        foreach ($roles as $role) {
            $email = $role->code.'@gmail.com';
            $user = User::withoutGlobalScopes()->where('pdam_org_id', $orgId)->where('email', $email)->first();

            if (! $user) {
                $display = RoleTemplateSeeder::ROLES[$role->code] ?? $role->name;
                $user = new User([
                    'name' => $display.' — '.self::NAME,
                    'email' => $email,
                    'password' => Hash::make(self::DEFAULT_PASSWORD),
                    'is_tenant_admin' => $role->code === 'admin_tenant',
                    'is_active' => true,
                ]);
                $user->pdam_org_id = $orgId;
                $user->save();
            }

            if (! $user->roles()->where('roles.id', $role->id)->exists()) {
                $user->roles()->attach($role->id);
            }
        }
    }


    private function seedMasterFinance(): void
    {
        foreach (self::ACCOUNTS as [$code, $name, $type, $normal]) {
            ChartOfAccount::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => $type, 'normal_balance' => $normal, 'is_active' => true],
            );
        }

        foreach (self::TARIFFS as [$code, $name, $group, $prices]) {
            $category = TariffCategory::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'group_type' => $group,
                    'abonemen' => $group === 'rumah_tangga' ? 5000 : 0,
                    'meter_maintenance_fee' => 2500,
                    'admin_fee' => 2500,
                    'minimum_usage_m3' => 10,
                    'is_active' => true,
                ],
            );

            if ($category->tiers()->exists()) {
                continue;
            }
            $bounds = [[0, 10], [10, 20], [20, null]];
            foreach ($bounds as $i => [$min, $max]) {
                $category->tiers()->create([
                    'pdam_org_id' => $category->pdam_org_id,
                    'tier_order' => $i + 1,
                    'min_usage' => $min,
                    'max_usage' => $max,
                    'price_per_m3' => $prices[$i],
                    'effective_date' => '2025-09-01',
                ]);
            }
        }

        BillingSetting::firstOrCreate(
            ['pdam_org_id' => TenantContext::id()],
            ['penalty_flat' => 5000, 'penalty_percent' => 2.0, 'due_day' => 20, 'isolir_after_months' => 6],
        );
    }

    private function seedAddress(): void
    {
        $kalbar = DB::table('provinces')->where('code', '61')->first();
        $provinceId = $kalbar?->id ?? DB::table('provinces')->insertGetId([
            'code' => '61', 'name' => 'Kalimantan Barat', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $city = DB::table('cities')->where('code', '6101')->first();
        $cityId = $city?->id ?? DB::table('cities')->insertGetId([
            'province_id' => $provinceId, 'code' => '6101', 'name' => 'Sambas', 'type' => 'kabupaten',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $orgId = TenantContext::id();
        foreach ($this->sambasDistricts() as [$dCode, $dName, $villages]) {
            if (DB::table('districts')->where('code', $dCode)->exists()) {
                continue;
            }
            $districtId = DB::table('districts')->insertGetId([
                'city_id' => $cityId, 'code' => $dCode, 'name' => $dName, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($villages as [$vCode, $vName, $postal, $streets]) {
                if (DB::table('villages')->where('code', $vCode)->exists()) {
                    continue;
                }
                $villageId = DB::table('villages')->insertGetId([
                    'district_id' => $districtId, 'code' => $vCode, 'name' => $vName,
                    'postal_code' => $postal, 'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($streets as $streetName) {
                    if (DB::table('streets')->where('village_id', $villageId)->where('name', $streetName)->exists()) {
                        continue;
                    }
                    DB::table('streets')->insert([
                        'pdam_org_id' => $orgId, 'village_id' => $villageId, 'name' => $streetName,
                        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    private function sambasDistricts(): array
    {
        return [
            ['610101', 'Sambas', [
                ['6101011', 'Dalam Kaum', '79460', ['Jl. Raya Sambas', 'Jl. Merdeka', 'Jl. Aloei Saboe']],
                ['6101012', 'Durian', '79460', ['Jl. Kapten Dolfien', 'Jl. Setia Bakti']],
                ['6101013', 'Pasar Melayu', '79460', ['Jl. Pasar Melayu', 'Jl. Melati']],
                ['6101014', 'Tanjung Bugis', '79460', ['Jl. Tanjung Bugis', 'Jl. Mawar']],
                ['6101015', 'Saing Rambi', '79460', ['Jl. Saing Rambi', 'Jl. Anggrek']],
                ['6101016', 'Tumuk Manggis', '79460', ['Jl. Tani', 'Jl. Cempaka']],
                ['6101017', 'Sumber Harapan', '79460', ['Jl. Gusti Hamzah', 'Jl. Kamboja']],
            ]],
            ['610102', 'Teluk Keramat', [
                ['6101021', 'Sekura', '79469', ['Jl. Raya Teluk Keramat', 'Jl. Alur Serumpun']],
                ['6101022', 'Teluk Kecapi', '79469', ['Jl. Raya Pantai', 'Jl. Nelayan']],
            ]],
            ['610103', 'Jawai', [
                ['6101031', 'Sentebang', '79454', ['Jl. Raya Jawai', 'Jl. Veteran']],
                ['6101032', 'Sarang Burung', '79454', ['Jl. Pahlawan', 'Jl. Cendana']],
            ]],
            ['610104', 'Tebas', [
                ['6101041', 'Tebas Kuala', '79461', ['Jl. Raya Tebas', 'Jl. Diponegoro', 'Jl. Ahmad Yani']],
                ['6101042', 'Sungai Kelambu', '79461', ['Jl. Beringin', 'Jl. Pinang']],
            ]],
            ['610105', 'Pemangkat', [
                ['6101051', 'Pemangkat Kota', '79455', ['Jl. Raya Pemangkat', 'Jl. Sutoyo', 'Jl. Pemuda']],
                ['6101052', 'Buduk', '79455', ['Jl. Perkebunan', 'Jl. Raya Lintas']],
            ]],
            ['610106', 'Sejangkung', [
                ['6101061', 'Parit Raja', '79463', ['Jl. Raya Sejangkung', 'Jl. Tani Makmur']],
                ['6101062', 'Penganjong', '79463', ['Jl. Pal 10', 'Jl. Perkebunan Kelapa']],
            ]],
            ['610107', 'Selakau', [
                ['6101071', 'Sungai Nyirih', '79452', ['Jl. Raya Selakau', 'Jl. Syamsuddin']],
                ['6101072', 'Sungai Daun', '79452', ['Jl. Melayu', 'Jl. Nusa Indah']],
            ]],
            ['610108', 'Paloh', [
                ['6101081', 'Liku', '79466', ['Jl. Raya Paloh', 'Jl. Pantai Liku']],
                ['6101082', 'Sebubus', '79466', ['Jl. Nelayan Sejahtera', 'Jl. Ikan Laut']],
            ]],
            ['610109', 'Sajingan Besar', [
                ['6101091', 'Kaliau', '79467', ['Jl. Raya Sajingan', 'Jl. Perbatasan']],
            ]],
            ['610110', 'Subah', [
                ['6101101', 'Balai Gemuruh', '79417', ['Jl. Raya Subah', 'Jl. Kebun Sawit']],
                ['6101102', 'Karaban', '79417', ['Jl. Tani Mandiri', 'Jl. Parit']],
            ]],
            ['610111', 'Galing', [
                ['6101111', 'Galing', '79453', ['Jl. Raya Galing', 'Jl. Karya Bakti']],
                ['6101112', 'Sungai Rambatan', '79453', ['Jl. Persada', 'Jl. Sungai']],
            ]],
            ['610112', 'Tekarang', [['6101121', 'Tekarang', '79468', ['Jl. Raya Tekarang', 'Jl. Teluk Suak']]]],
            ['610113', 'Semparuk', [['6101131', 'Semparuk', '79457', ['Jl. Raya Semparuk', 'Jl. Persitar']]]],
            ['610114', 'Sajad', [['6101141', 'Tengguli', '79462', ['Jl. Raya Sajad', 'Jl. Jati']]]],
            ['610115', 'Sebawi', [['6101151', 'Sebawi', '79464', ['Jl. Raya Sebawi', 'Jl. Padi']]]],
            ['610116', 'Jawai Selatan', [['6101161', 'Matang Terap', '79154', ['Jl. Raya Jawai Selatan', 'Jl. Sawah']]]],
            ['610117', 'Tangaran', [['6101171', 'Simpang Empat', '79465', ['Jl. Raya Tangaran', 'Jl. Simpang']]]],
            ['610118', 'Salatiga', [['6101181', 'Salatiga', '79456', ['Jl. Raya Salatiga', 'Jl. Nelayan']]]],
            ['610119', 'Selakau Timur', [['6101191', 'Selakau Tua', '79451', ['Jl. Raya Selakau Timur', 'Jl. Karet']]]],
        ];
    }

    private function seedZones(): array
    {
        $zones = [];
        foreach (self::ZONES as $i => [$code, $name, $districtCodes, $isMain]) {
            $zones[$i] = Zone::firstOrCreate(
                ['pdam_org_id' => TenantContext::id(), 'code' => $code],
                ['name' => $name, 'office_address' => 'Kantor '.$name, 'office_phone' => '0561-733'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), 'is_main' => $isMain, 'is_active' => true],
            );
        }

        return $zones;
    }

    private function seedWarehouses(array $zones): array
    {
        $warehouses = [];
        foreach ($zones as $i => $zone) {
            $warehouses[$i] = Warehouse::firstOrCreate(
                ['pdam_org_id' => TenantContext::id(), 'code' => 'WH-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)],
                ['zone_id' => $zone->id, 'name' => 'Gudang '.$zone->name, 'warehouse_type' => $i === 0 ? 'main' : 'buffer', 'address' => 'Gudang '.$zone->name, 'is_active' => true],
            );
        }

        return $warehouses;
    }


    private function seedSuppliers(): void
    {
        $rows = [
            ['SUP-01', 'CV Khatulistiwa Sanitasi', '0561-733001', 'Jl. Raya Sambas No. 12'],
            ['SUP-02', 'PT Meter Kalbar Sejahtera', '0561-733002', 'Jl. Ahmad Yani No. 88'],
            ['SUP-03', 'CV Kimia Tirta Sambas', '0561-733003', 'Jl. Raya Pemangkat No. 5'],
            ['SUP-04', 'PT Baja Pipa Borneo', '0561-733004', 'Jl. Raya Tebas No. 21'],
        ];
        foreach ($rows as [$code, $name, $phone, $address]) {
            Supplier::firstOrCreate(
                ['pdam_org_id' => TenantContext::id(), 'code' => $code],
                ['name' => $name, 'phone' => $phone, 'address' => $address, 'is_active' => true],
            );
        }
    }

    private function seedMaterials(): array
    {
        $rows = [
            ['MTL-TWAS-25', 'Tawas (Aluminium Sulfat)', 'kimia', 'kg', 12000],
            ['MTL-KPRT-25', 'Kaporit 60%', 'kimia', 'kg', 35000],
            ['MTL-PAC-25', 'PAC (Poly Aluminium Chloride)', 'kimia', 'kg', 18000],
            ['MTL-PIPA-3', 'Pipa PVC 3 inci', 'pipa', 'batang', 85000],
            ['MTL-PIPA-4', 'Pipa PVC 4 inci', 'pipa', 'batang', 120000],
            ['MTL-MTR-05', 'Water Meter 1/2 inci', 'meter', 'unit', 175000],
            ['MTL-VLV-05', 'Gate Valve 1/2 inci', 'aksesoris', 'unit', 45000],
            ['MTL-CLM-05', 'Clamp Saddle 1/2 inci', 'aksesoris', 'unit', 22000],
        ];
        $materials = [];
        foreach ($rows as [$code, $name, $category, $unit, $price]) {
            $materials[$code] = Material::firstOrCreate(
                ['pdam_org_id' => TenantContext::id(), 'code' => $code],
                ['name' => $name, 'category' => $category, 'unit' => $unit, 'last_price' => $price, 'is_active' => true],
            );
        }

        return $materials;
    }

    private function seedCapital(): void
    {
        $amount = (float) env('SEED_SAMBAS_CAPITAL', 7500000000);
        $this->journal->record(
            'Setoran modal awal pendirian '.self::NAME,
            [
                ['account_code' => '1-001', 'type' => 'DEBIT', 'amount' => $amount, 'memo' => 'Kas awal'],
                ['account_code' => '3-001', 'type' => 'KREDIT', 'amount' => $amount, 'memo' => 'Modal disetor'],
            ],
            'CAPITAL', null, '2025-09-01',
        );
    }

    private function seedInitialStock(array $materials, array $warehouses): void
    {
        $main = $warehouses[0];
        $rows = [
            ['MTL-TWAS-25', 2000, 400], ['MTL-KPRT-25', 800, 200], ['MTL-PAC-25', 1000, 300],
            ['MTL-PIPA-3', 600, 60], ['MTL-PIPA-4', 400, 50], ['MTL-MTR-05', 1500, 200],
            ['MTL-VLV-05', 500, 50], ['MTL-CLM-05', 800, 60],
        ];
        $totalPurchase = 0.0;
        foreach ($rows as [$code, $qty, $minStock]) {
            $material = $materials[$code];
            $this->stock->stockIn($material->id, $main->id, $qty, 'initial_purchase', null, null);
            MaterialStock::where('material_id', $material->id)->where('warehouse_id', $main->id)->update(['minimum_stock' => $minStock]);
            $totalPurchase += $qty * (float) $material->last_price;
        }
        $this->journal->record(
            'Pembelian material persediaan awal (tunai)',
            [
                ['account_code' => '1-003', 'type' => 'DEBIT', 'amount' => $totalPurchase, 'memo' => 'Persediaan material'],
                ['account_code' => '1-001', 'type' => 'KREDIT', 'amount' => $totalPurchase, 'memo' => 'Kas keluar'],
            ],
            'STOCK_PURCHASE', null, '2025-09-02',
        );
    }

    private function seedRoutes(array $zones): array
    {
        $routes = [];
        foreach ($zones as $i => $zone) {
            $routes[$i] = MeterRoute::firstOrCreate(
                ['pdam_org_id' => TenantContext::id(), 'code' => 'RT-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)],
                ['zone_id' => $zone->id, 'name' => 'Rute '.$zone->name, 'is_active' => true],
            );
        }

        return $routes;
    }

    private function seedRouteStreets(): void
    {
        $orgId = TenantContext::id();
        $zoneByDistrict = [];
        foreach (self::ZONES as $idx => [$code, $name, $districtCodes, $isMain]) {
            foreach ($districtCodes as $d) {
                $zoneByDistrict[$d] = $idx;
            }
        }
        $routes = DB::table('meter_routes')->where('pdam_org_id', $orgId)->get()->keyBy('code');
        $streets = DB::table('streets')->where('pdam_org_id', $orgId)->get();

        foreach ($streets as $street) {
            $village = DB::table('villages')->where('id', $street->village_id)->first();
            $district = $village ? DB::table('districts')->where('id', $village->district_id)->first() : null;
            if (! $district || ! isset($zoneByDistrict[$district->code])) {
                continue;
            }
            $route = $routes['RT-'.str_pad((string) ($zoneByDistrict[$district->code] + 1), 2, '0', STR_PAD_LEFT)] ?? null;
            if (! $route) {
                continue;
            }
            DB::table('meter_route_streets')->insertOrIgnore([
                'pdam_org_id' => $orgId, 'meter_route_id' => $route->id, 'street_id' => $street->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function seedRouteAssignments(array $routes): void
    {
        $orgId = TenantContext::id();
        $officerRole = Role::withoutGlobalScopes()->where('pdam_org_id', $orgId)->where('code', 'meter_officer')->first();
        $names = ['Rahmat Hidayat', 'Sutrisno', 'Junaidi', 'M. Fajar', 'Alexius Ucok', 'Hendra Gunawan', 'Ahmad Zulkifli'];

        foreach ($routes as $i => $route) {
            $email = 'meter_officer'.($i + 1).'@gmail.com';
            $officer = User::withoutGlobalScopes()->where('pdam_org_id', $orgId)->where('email', $email)->first();
            if (! $officer) {
                $officer = new User([
                    'name' => $names[$i % count($names)].' — Petugas Baca Meter '.$route->name,
                    'email' => $email, 'password' => Hash::make(self::DEFAULT_PASSWORD), 'is_active' => true,
                ]);
                $officer->pdam_org_id = $orgId;
                $officer->save();
            }
            if ($officerRole && ! $officer->roles()->where('roles.id', $officerRole->id)->exists()) {
                $officer->roles()->attach($officerRole->id);
            }
            MeterRouteAssignment::updateOrCreate(
                ['pdam_org_id' => $orgId, 'meter_route_id' => $route->id],
                ['officer_id' => $officer->id, 'is_active' => true],
            );
        }
    }

    private function seedReadingPeriods(): void
    {
        $orgId = TenantContext::id();
        foreach ($this->billingPeriods() as $period) {
            ReadingPeriod::firstOrCreate(
                ['pdam_org_id' => $orgId, 'period' => $period],
                ['status' => 'closed', 'opened_at' => $period.'-01 08:00:00', 'closed_at' => $period.'-25 17:00:00'],
            );
        }
    }

    private function billingPeriods(): array
    {
        $periods = [];
        $start = new \DateTimeImmutable('2025-09-01');
        $cur = new \DateTimeImmutable(date('Y-m-01'));
        $lastFull = $cur->sub(new \DateInterval('P1M'));
        $p = $start;
        while ($p <= $lastFull) {
            $periods[] = $p->format('Y-m');
            $p = $p->add(new \DateInterval('P1M'));
        }

        return $periods;
    }


    private function seedCustomersAndBilling(array $zones, array $routes, array $warehouses): void
    {
        $orgId = TenantContext::id();
        $periods = $this->billingPeriods();
        $total = count($periods);

        $verifier = User::withoutGlobalScopes()->where('pdam_org_id', $orgId)->where('email', 'meter_office@gmail.com')->first();

        $tariffs = TariffCategory::where('is_active', true)->get()->keyBy('code');
        $streetIds = DB::table('streets')->where('pdam_org_id', $orgId)->pluck('id')->all();

        $readerByRoute = [];
        foreach (DB::table('meter_route_assignments')->where('pdam_org_id', $orgId)->get() as $a) {
            $readerByRoute[$a->meter_route_id] = $a->officer_id;
        }

        // Cache konfigurasi tarif (hindari query berulang per tagihan).
        $tariffConfigs = [];
        foreach (TariffCategory::with('tiers')->where('is_active', true)->get() as $tc) {
            $tariffConfigs[$tc->id] = [
                'min_usage' => (int) $tc->minimum_usage_m3,
                'abonemen' => (float) $tc->abonemen,
                'meter_fee' => (float) $tc->meter_maintenance_fee,
                'admin_fee' => (float) $tc->admin_fee,
                'tiers' => $tc->tiers->map(fn ($t) => ['order' => $t->tier_order, 'min' => (int) $t->min_usage, 'max' => $t->max_usage !== null ? (int) $t->max_usage : null, 'price' => (float) $t->price_per_m3])->sortBy('order')->values()->all(),
            ];
        }
        $coaIds = ChartOfAccount::pluck('id', 'code');
        $dueDay = (int) (BillingSetting::first()?->due_day ?? 20);
        $now = now();

        $bar = $this->command?->getOutput()->createProgressBar($this->customerCount);
        $bar?->start();

        DB::transaction(function () use ($orgId, $zones, $routes, $periods, $total, $verifier, $tariffs, $streetIds, $readerByRoute, $tariffConfigs, $coaIds, $dueDay, $now, $bar) {
            $billCounter = [];
            $jeCounter = [];
            foreach ($periods as $p) {
                $billCounter[$p] = DB::table('bills')->where('pdam_org_id', $orgId)->where('period', $p)->count();
                $jeCounter[$p] = DB::table('journal_entries')->where('pdam_org_id', $orgId)->where('period', $p)->count();
            }

            for ($i = 0; $i < $this->customerCount; $i++) {
            $zoneIdx = $i % count($zones);
            $zone = $zones[$zoneIdx];
            $route = $routes[$zoneIdx];
            $readerId = $readerByRoute[$route->id] ?? null;

            [$tariffCode, $tariff] = $this->pickTariff($tariffs, $i);
            $usageBase = $this->baseUsage($tariff->group_type, $i);
            $cfg = $tariffConfigs[$tariff->id] ?? $tariffConfigs[array_key_first($tariffConfigs)];

            $customerName = $this->customerName($i, $tariff->group_type);
            $streetId = $streetIds[$i % max(1, count($streetIds))];
            $customerNumber = sprintf('SMBS-%05d', $i + 1);
            $serial = 'MTR-'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT);
            $installDate = '2025-09-01';

            $customer = Customer::create([
                'pdam_org_id' => $orgId,
                'customer_number' => $customerNumber,
                'full_name' => $customerName,
                'phone' => '08125'.str_pad((string) (670000 + $i), 6, '0', STR_PAD_LEFT),
                'zone_id' => $zone->id,
                'street_id' => $streetId,
                'address_detail' => 'No. '.(($i % 120) + 1).', RT 0'.(($i % 8) + 1).'/RW 0'.(($i % 5) + 1),
                'latitude' => round(1.35 + (($i % 100) / 1000), 7),
                'longitude' => round(109.30 + (($i % 100) / 800), 7),
                'tariff_category_id' => $tariff->id,
                'meter_serial_number' => $serial,
                'meter_route_id' => $route->id,
                'installation_date' => $installDate,
                'initial_reading' => 0,
                'status' => 'active',
            ]);

            Meter::create([
                'pdam_org_id' => $orgId, 'serial_number' => $serial, 'brand' => 'Amico', 'model' => 'B-Meter 1/2"',
                'diameter' => '0.5', 'install_year' => 2025, 'install_date' => $installDate, 'condition' => 'baik',
                'status' => 'terpasang', 'tamper_status' => 'normal', 'customer_id' => $customer->id,
            ]);

            $previousReading = 0;
            $previousReadingId = null;
            $bucket = $this->paymentBucket($i);

            foreach ($periods as $pIdx => $period) {
                $usage = max(1, (int) round($usageBase * (0.75 + (($i * 7 + $pIdx * 13) % 10) / 20)));
                $currentReading = $previousReading + $usage;

                // ── Baca meter (foto + petugas) — langsung insert ──
                $readingId = DB::table('meter_readings')->insertGetId([
                    'pdam_org_id' => $orgId, 'customer_id' => $customer->id, 'period' => $period,
                    'reading_value' => $currentReading, 'reading_date' => $period.'-20',
                    'photo_house_url' => 'storage/meter_photos/sambas/'.$period.'/house-'.$customerNumber.'.jpg',
                    'photo_meter_url' => 'storage/meter_photos/sambas/'.$period.'/meter-'.$customerNumber.'.jpg',
                    'reading_type' => ($i % 23 === 0) ? 'manual_corrected' : ($i % 41 === 0 ? 'estimated' : 'ocr_confirmed'),
                    'unreadable_reason' => ($i % 41 === 0) ? 'Meter berdebu / angka sulit dibaca' : null,
                    'is_flagged' => ($i % 17 === 0) ? 1 : 0,
                    'flag_reason' => ($i % 17 === 0) ? 'Deviasi konsumsi di atas 30% dari rata-rata' : null,
                    'is_rollover' => 0,
                    'read_by' => $readerId, 'verified_by' => $verifier?->id ?? $readerId,
                    'verified_at' => $period.'-22 10:00:00',
                    'created_at' => $now, 'updated_at' => $now,
                ]);

                // ── Hitung pemakaian & tarif bertingkat (cache) ──
                $consumption = $currentReading - $previousReading;
                if ($consumption < 0) {
                    $consumption = ($currentReading + 100000) - $previousReading;
                }
                $calc = $this->calcTariff($cfg, $consumption);

                // ── Tagihan ──
                $billCounter[$period] = ($billCounter[$period] ?? 0) + 1;
                $billNumber = sprintf('INV-%d-%s-%05d', $orgId, str_replace('-', '', $period), $billCounter[$period]);
                $dueDate = $this->buildDueDate($period, $dueDay);
                $billId = DB::table('bills')->insertGetId([
                    'pdam_org_id' => $orgId, 'customer_id' => $customer->id, 'bill_number' => $billNumber,
                    'period' => $period, 'previous_reading_id' => $previousReadingId, 'current_reading_id' => $readingId,
                    'previous_reading' => $previousReading, 'current_reading' => $currentReading,
                    'consumption' => $consumption, 'water_charge' => $calc['water_charge'],
                    'abonemen' => $cfg['abonemen'], 'meter_maintenance_fee' => $cfg['meter_fee'], 'admin_fee' => $cfg['admin_fee'],
                    'penalty' => 0, 'amount_due' => $calc['total'], 'status' => 'unpaid', 'due_date' => $dueDate,
                    'created_at' => $now, 'updated_at' => $now,
                ]);

                // rincian item tagihan
                foreach ($calc['components'] as $comp) {
                    DB::table('bill_items')->insert([
                        'pdam_org_id' => $orgId, 'bill_id' => $billId, 'component' => $comp['component'],
                        'label' => $comp['label'], 'quantity' => $comp['quantity'],
                        'unit_price' => $comp['unit_price'], 'amount' => $comp['amount'],
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }

                // Jurnal tagihan: DEBIT Piutang | KREDIT Pendapatan Air
                $entryId = $this->insertJournal($orgId, "Tagihan {$period} - {$customerNumber}", [
                    ['account_id' => $coaIds['1-002'], 'type' => 'DEBIT', 'amount' => $calc['total'], 'memo' => 'Piutang pelanggan'],
                    ['account_id' => $coaIds['4-001'], 'type' => 'KREDIT', 'amount' => $calc['total'], 'memo' => 'Pendapatan air'],
                ], 'BILL', $billId, $period.'-20', $jeCounter);
                DB::table('bills')->where('id', $billId)->update(['journal_entry_id' => $entryId]);

                // ── Pembayaran ──
                if ($this->shouldPay($pIdx, $total, $bucket)) {
                    $paymentNumber = 'PAY-'.$orgId.'-'.strtoupper(uniqid());
                    $paymentId = DB::table('payments')->insertGetId([
                        'pdam_org_id' => $orgId, 'payment_type' => 'monthly_bill', 'bill_id' => $billId,
                        'customer_id' => $customer->id, 'payment_number' => $paymentNumber,
                        'amount' => $calc['total'], 'payment_method' => 'tunai', 'channel' => 'cash',
                        'midtrans_order_id' => 'ORD-'.uniqid(), 'status' => 'success', 'paid_at' => $now,
                        'received_by' => null, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $payEntryId = $this->insertJournal($orgId, "Pembayaran tagihan {$billNumber}", [
                        ['account_id' => $coaIds['1-001'], 'type' => 'DEBIT', 'amount' => $calc['total'], 'memo' => 'Kas/Bank'],
                        ['account_id' => $coaIds['1-002'], 'type' => 'KREDIT', 'amount' => $calc['total'], 'memo' => 'Pelunasan piutang'],
                    ], 'PAYMENT', $paymentId, $now->toDateString(), $jeCounter);
                    DB::table('bills')->where('id', $billId)->update(['status' => 'paid']);
                    DB::table('payments')->where('id', $paymentId)->update(['journal_entry_id' => $payEntryId]);
                }

                // Pelanggan putus (bucket 4): >6 bulan tidak bayar.
                if ($bucket === 4 && $pIdx === $total - 7) {
                    if (! DB::table('customers')->where('id', $customer->id)->where('status', 'isolir')->exists()) {
                        DB::table('customers')->where('id', $customer->id)->update(['status' => 'isolir']);
                        DB::table('customer_status_history')->insert([
                            'pdam_org_id' => $orgId, 'customer_id' => $customer->id,
                            'from_status' => 'active', 'to_status' => 'isolir',
                            'reason' => 'Tunggakan lebih dari 6 bulan tidak dibayar.',
                            'changed_by' => $verifier?->id ?? null, 'created_at' => $now,
                        ]);
                    }
                }

                $previousReading = $currentReading;
                $previousReadingId = $readingId;
            }

            if (($i % 50) === 0) {
                $bar?->advance(50);
            }
            }
        });

        $bar?->finish();
        $this->command?->getOutput()->writeln('');
    }

    /** Hitung tagihan tarif bertingkat dari cache. */
    private function calcTariff(array $cfg, int $consumption): array
    {
        $billable = max($consumption, $cfg['min_usage']);
        $waterCharge = 0.0;
        $remaining = $billable;
        $components = [];

        foreach ($cfg['tiers'] as $t) {
            if ($remaining <= 0) {
                break;
            }
            $capacity = $t['max'] === null ? $remaining : max(0, $t['max'] - $t['min']);
            $units = $t['max'] === null ? $remaining : min($remaining, $capacity);
            if ($units <= 0) {
                continue;
            }
            $amt = round($units * $t['price'], 2);
            $waterCharge += $amt;
            $remaining -= $units;
            $components[] = [
                'component' => 'tier_'.$t['order'], 'label' => 'Pemakaian tier '.$t['order'].' ('.$units.' m³ × Rp '.number_format($t['price'], 0, ',', '.').')',
                'quantity' => $units, 'unit_price' => $t['price'], 'amount' => $amt,
            ];
        }

        $abonemen = $cfg['abonemen'];
        $meterFee = $cfg['meter_fee'];
        $adminFee = $cfg['admin_fee'];
        if ($abonemen > 0) {
            $components[] = ['component' => 'abonemen', 'label' => 'Biaya beban tetap (abonemen)', 'quantity' => 0, 'unit_price' => $abonemen, 'amount' => $abonemen];
        }
        if ($meterFee > 0) {
            $components[] = ['component' => 'meter_maintenance', 'label' => 'Pemeliharaan meter', 'quantity' => 0, 'unit_price' => $meterFee, 'amount' => $meterFee];
        }
        if ($adminFee > 0) {
            $components[] = ['component' => 'admin', 'label' => 'Biaya administrasi', 'quantity' => 0, 'unit_price' => $adminFee, 'amount' => $adminFee];
        }
        $total = round($waterCharge + $abonemen + $meterFee + $adminFee, 2);

        return ['water_charge' => round($waterCharge, 2), 'components' => $components, 'total' => $total];
    }

    /** Jurnal double-entry langsung (SEIMBANG: DEBIT == KREDIT wajib). */
    private function insertJournal(int $orgId, string $description, array $lines, ?string $refType, ?int $refId, string $entryDate, array &$jeCounter): int
    {
        $period = substr($entryDate, 0, 7);
        $jeCounter[$period] = ($jeCounter[$period] ?? 0) + 1;
        $entryNumber = sprintf('JE-%d-%s-%04d', $orgId, str_replace('-', '', $period), $jeCounter[$period]);

        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $l) {
            $l['type'] === 'DEBIT' ? $debit += $l['amount'] : $credit += $l['amount'];
        }
        if (abs($debit - $credit) > 0.001) {
            throw new \RuntimeException("Jurnal tidak balance: DEBIT {$debit} != KREDIT {$credit}. Dibataikan.");
        }

        $entryId = DB::table('journal_entries')->insertGetId([
            'pdam_org_id' => $orgId, 'entry_number' => $entryNumber, 'entry_date' => $entryDate,
            'period' => $period, 'description' => $description, 'reference_type' => $refType,
            'reference_id' => $refId, 'created_by' => 'seeder', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($lines as $l) {
            DB::table('journal_entry_lines')->insert([
                'pdam_org_id' => $orgId, 'journal_id' => $entryId, 'account_id' => $l['account_id'],
                'type' => $l['type'], 'amount' => $l['amount'], 'memo' => $l['memo'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $entryId;
    }

    private function buildDueDate(string $period, int $dueDay): string
    {
        [$y, $m] = explode('-', $period);
        $day = min($dueDay, (int) date('t', mktime(0, 0, 0, (int) $m, 1, (int) $y)));

        return sprintf('%04d-%02d-%02d', $y, $m, $day);
    }

    private function shouldPay(int $periodIdx, int $totalPeriods, int $bucket): bool
    {
        return match ($bucket) {
            0 => true,
            1 => $periodIdx < $totalPeriods - 1,
            2 => $periodIdx < $totalPeriods - 2,
            3 => $periodIdx < $totalPeriods - 3,
            4 => $periodIdx < $totalPeriods - 7,
            default => true,
        };
    }

    private function pickTariff($tariffs, int $i): array
    {
        $m = $i % 100;
        if ($m < 58) { $code = ['2A1', '2A2', '2A3', '2D', '2B'][$i % 5]; }
        elseif ($m < 66) { $code = ['3A', '3B'][$i % 2]; }
        elseif ($m < 74) { $code = ['4A', '4B', '4C'][$i % 3]; }
        elseif ($m < 80) { $code = ['1A', '1B', '1C'][$i % 3]; }
        elseif ($m < 83) { $code = '2F'; }
        elseif ($m < 88) { $code = '3C'; }
        elseif ($m < 93) { $code = ['4B', '4C'][$i % 2]; }
        else { $code = ['5A', '5B'][$i % 2]; }

        $tariff = $tariffs[$code] ?? $tariffs['2A2'];

        return [$code, $tariff];
    }

    private function baseUsage(string $group, int $i): int
    {
        return match ($group) {
            'rumah_tangga' => 8 + ($i % 12),
            'sosial' => 5 + ($i % 10),
            'niaga' => 25 + ($i % 30),
            'industri' => 60 + ($i % 90),
            'khusus' => 300 + ($i % 400),
            default => 10,
        };
    }

    private function customerName(int $i, string $group): string
    {
        $first = ['Haji', 'Ahmad', 'Muhammad', 'Budi', 'Hendra', 'Siti', 'Nur', 'Dewi', 'Yusuf', 'Rahmat', 'Joko', 'Andi', 'Slamet', 'Kartini', 'Mainunah', 'Zainal', 'Nadia', 'Bambang', 'Sopian', 'Tuminah', 'Hasan', 'Ali', 'Ratna', 'Fitri', 'Dwina', 'Gito', 'Wahyudi', 'Eko', 'Sutrisno', 'Rudi'];
        $last = ['Santoso', 'Wijaya', 'Hidayat', 'Ramadhan', 'Susanto', 'Pratama', 'Setiawan', 'Gunawan', 'Saputra', 'Maulana', 'Firdaus', 'Nugroho', 'Priyanto', 'Hartono', 'Kurniawan', 'Yusuf'];

        if ($group === 'niaga') {
            return ['Toko Sembako ', 'Warung Kopi ', 'Bengkel ', 'Toko Kelontong ', 'Kios Pulsa ', 'RM. ', 'Pasar ', 'Kios '][$i % 8].$first[$i % 30];
        }
        if ($group === 'industri') {
            return ['CV ', 'PT ', 'UD ', 'Pabrik ', 'Industri '][$i % 5].$last[$i % 16].' '.$first[$i % 30];
        }
        if ($group === 'khusus') {
            return ['Kantor Kecamatan ', 'Kantor Camat ', 'Masjid ', 'Puskesmas ', 'Madrasah ', 'RSUD ', 'Polsek ', 'Sekolah '][$i % 8].$first[$i % 30].' Sambas';
        }
        if ($group === 'sosial') {
            return ['Masjid ', 'Musholla ', 'Panti Asuhan ', 'MADRASAH ', 'Gereja ', 'Vihara '][$i % 6].'Al-'.$first[$i % 30];
        }

        return $first[$i % 30].' '.$last[$i % 16];
    }

    private function seedDisconnections(): void
    {
        $orgId = TenantContext::id();
        $isolated = Customer::withoutTenant()->where('pdam_org_id', $orgId)->where('status', 'isolir')->limit(40)->get();

        foreach ($isolated as $customer) {
            if (DB::table('disconnections')->where('customer_id', $customer->id)->exists()) {
                continue;
            }
            DB::table('disconnections')->insert([
                'pdam_org_id' => $orgId, 'customer_id' => $customer->id, 'type' => 'isolir',
                'reason' => 'Tunggakan lebih dari 6 bulan tidak dibayar',
                'effective_date' => now()->subMonths(2)->toDateString(), 'requested_by' => null,
                'created_at' => now()->subMonths(2), 'updated_at' => now()->subMonths(2),
            ]);
        }
    }


    private function seedWarehouseOperations(array $warehouses, array $materials): void
    {
        $orgId = TenantContext::id();
        $requester = User::withoutGlobalScopes()->where('pdam_org_id', $orgId)->where('email', 'warehouse_staff@gmail.com')->first();
        $approver = User::withoutGlobalScopes()->where('pdam_org_id', $orgId)->where('email', 'warehouse_head@gmail.com')->first();

        if (DB::table('purchase_orders')->where('pdam_org_id', $orgId)->exists()) {
            return;
        }

        $mainId = $warehouses[0]->id;
        $buffer = $warehouses[1] ?? null;

        // Transfer stok: gudang wilayah (buffer) -> gudang pusat (main).
        if ($buffer) {
            $transferId = DB::table('stock_transfers')->insertGetId([
                'pdam_org_id' => $orgId, 'transfer_number' => sprintf('TRF-%d-0001', $orgId),
                'transfer_type' => 'buffer_to_main', 'from_warehouse_id' => $buffer->id, 'to_warehouse_id' => $mainId,
                'reason' => 'reorder', 'status' => 'completed', 'requested_by' => $requester?->id,
                'approved_by' => $approver?->id, 'received_by' => $requester?->id,
                'notes' => 'Permintaan barang gudang wilayah Pemangkat ke gudang pusat Sambas.',
                'completed_at' => now()->subDays(3), 'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(3),
            ]);
            foreach ([$materials['MTL-TWAS-25'], $materials['MTL-VLV-05']] as $m) {
                DB::table('stock_transfer_items')->insert([
                    'pdam_org_id' => $orgId, 'transfer_id' => $transferId, 'material_id' => $m->id,
                    'quantity_requested' => 150, 'quantity_received' => 150, 'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(3),
                ]);
            }
        }

        // Opname (adjustment) di gudang pusat.
        $twas = $materials['MTL-TWAS-25'];
        $sys = (float) (DB::table('material_stocks')->where('material_id', $twas->id)->where('warehouse_id', $mainId)->value('current_stock') ?? 100);
        $phys = $sys - 15;
        DB::table('stock_adjustments')->insert([
            'pdam_org_id' => $orgId, 'material_id' => $twas->id, 'warehouse_id' => $mainId,
            'system_stock' => $sys, 'physical_stock' => $phys, 'difference' => $phys - $sys,
            'reason' => 'opname', 'adjusted_by' => $requester?->id, 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
        ]);

        // Purchase Order tawas bekisting + pipa.
        $sup = DB::table('suppliers')->where('pdam_org_id', $orgId)->where('code', 'SUP-03')->first()
            ?? DB::table('suppliers')->where('pdam_org_id', $orgId)->first();
        $poItems = [
            ['material_id' => $twas->id, 'qty' => 2000, 'price' => (float) $twas->last_price],
            ['material_id' => $materials['MTL-PIPA-4']->id, 'qty' => 200, 'price' => (float) $materials['MTL-PIPA-4']->last_price],
        ];
        $poTotal = collect($poItems)->sum(fn ($it) => $it['qty'] * $it['price']);
        DB::table('purchase_orders')->insert([
            'pdam_org_id' => $orgId, 'po_number' => sprintf('PO-%d-0001', $orgId), 'supplier_id' => $sup?->id,
            'requested_by' => $requester?->id, 'items' => json_encode($poItems), 'total_estimated_price' => $poTotal,
            'urgency' => 'urgent', 'status' => 'approved', 'created_at' => now()->subDays(6), 'updated_at' => now()->subDays(4),
        ]);

        // Repair order (material keluar utk perbaikan).
        $cust = DB::table('customers')->where('pdam_org_id', $orgId)->first();
        DB::table('repair_orders')->insert([
            'pdam_org_id' => $orgId, 'order_number' => sprintf('RO-%d-0001', $orgId), 'customer_id' => $cust?->id,
            'description' => 'Perbaikan kebocoran pipa dinas & penggantian gate valve.',
            'materials_used' => json_encode([['material_id' => $materials['MTL-VLV-05']->id, 'qty' => 1]]),
            'warehouse_id' => $mainId, 'status' => 'completed', 'created_by' => $requester?->id,
            'completed_at' => now()->subDay(), 'created_at' => now()->subDays(2), 'updated_at' => now()->subDay(),
        ]);
    }

    private function seedProcurement(): void
    {
        $orgId = TenantContext::id();
        if (DB::table('vendors')->where('pdam_org_id', $orgId)->exists()) {
            return;
        }
        $vendorDefs = [
            ['VND-SMBS-01', 'PT Pipa Nusantara', 'material', 4.50],
            ['VND-SMBS-02', 'CV Kimia Tirta Sambas', 'kimia', 4.30],
            ['VND-SMBS-03', 'PT Teknik Pompa Borneo', 'peralatan', 4.10],
        ];
        $vendorIds = [];
        foreach ($vendorDefs as $i => [$code, $name, $category, $rating]) {
            $vendorIds[$code] = DB::table('vendors')->insertGetId([
                'pdam_org_id' => $orgId, 'code' => $code, 'name' => $name, 'npwp' => '01.234.567.8-90'.$i.'.000',
                'contact_name' => 'PIC '.$name, 'phone' => '0561-733'.$i.$i.'0', 'email' => 'sales'.$i.'@vendor.co.id',
                'address' => 'Sambas', 'category' => $category, 'rating' => $rating, 'is_blacklisted' => false,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $tenderId = DB::table('tenders')->insertGetId([
            'pdam_org_id' => $orgId, 'tender_number' => 'TDR-'.$orgId.'-0001',
            'title' => 'Pengadaan Tawas & Pipa Distribusi 2025/2026', 'category' => 'material',
            'budget_ceiling' => 400000000, 'publish_date' => now()->subDays(45)->toDateString(),
            'submission_deadline' => now()->subDays(28)->toDateString(), 'award_date' => now()->subDays(22)->toDateString(),
            'status' => 'awarded', 'winner_vendor_id' => $vendorIds['VND-SMBS-02'], 'final_price' => 372000000,
            'created_at' => now()->subDays(45), 'updated_at' => now()->subDays(22),
        ]);
        DB::table('tender_bids')->insert([
            'pdam_org_id' => $orgId, 'tender_id' => $tenderId, 'vendor_id' => $vendorIds['VND-SMBS-02'],
            'bid_price' => 372000000, 'technical_proposal' => 'Proposal teknis VND-SMBS-02', 'technical_score' => 90.0,
            'price_score' => 88.0, 'total_score' => 91.0, 'rank' => 1, 'created_at' => now()->subDays(26), 'updated_at' => now()->subDays(22),
        ]);
        $contractId = DB::table('vendor_contracts')->insertGetId([
            'pdam_org_id' => $orgId, 'vendor_id' => $vendorIds['VND-SMBS-02'], 'contract_number' => 'CTR-'.$orgId.'-0001',
            'title' => 'Kontrak Pengadaan Tawas & Pipa', 'start_date' => now()->subDays(20)->toDateString(),
            'end_date' => now()->addMonths(5)->toDateString(), 'value' => 372000000, 'status' => 'active',
            'tender_id' => $tenderId, 'created_at' => now()->subDays(20), 'updated_at' => now()->subDays(20),
        ]);
        DB::table('vendor_evaluations')->insert([
            'pdam_org_id' => $orgId, 'vendor_id' => $vendorIds['VND-SMBS-02'], 'contract_id' => $contractId,
            'quality_score' => 4.6, 'delivery_score' => 4.3, 'price_score' => 4.5, 'compliance_score' => 4.8,
            'overall_score' => 4.5, 'comments' => 'Kualitas tawas baik, pengiriman tepat waktu.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $requester = DB::table('users')->where('pdam_org_id', $orgId)->where('email', 'warehouse_staff@gmail.com')->first();
        $warehouse = DB::table('warehouses')->where('pdam_org_id', $orgId)->first();
        DB::table('purchase_requests')->insert([
            'pdam_org_id' => $orgId, 'pr_number' => 'PR-'.$orgId.'-0001', 'requested_by' => $requester?->id ?? 0,
            'warehouse_id' => $warehouse?->id, 'status' => 'approved', 'required_date' => now()->addDays(15)->toDateString(),
            'estimated_total' => 120000000, 'notes' => 'Tawas untuk IPA Sambas, stok menipis.',
            'created_at' => now()->subDays(7), 'updated_at' => now()->subDays(3),
        ]);
    }

    private function seedEnterpriseModules(int $orgId, array $zones, array $warehouses): void
    {
        $zone = $zones[0];
        $cust = DB::table('customers')->where('pdam_org_id', $orgId)->first();
        $meter = DB::table('meters')->where('pdam_org_id', $orgId)->first();
        $mainWarehouse = $warehouses[0];

        $this->seedMeterExtended($orgId);
        $this->seedAssets($orgId, $zone);
        $this->seedFinanceEnterprise($orgId);
        $this->seedChemicals($orgId, $mainWarehouse);
        $this->seedFieldService($orgId, $zones);
        $this->seedMaintenance($orgId);
        $this->seedHr($orgId);
        $this->seedDmsGisIntegration($orgId, $zone);
        $this->seedSmartUtility($orgId, $zone, $cust, $meter);
        $this->seedBillingExtra($orgId);
        $this->seedCrm($orgId);
        $this->seedSurvey($orgId, $zones);
        $this->seedNotifications($orgId, $cust);
    }


    private function seedMeterExtended(int $orgId): void
    {
        if (DB::table('meter_lifecycle_events')->where('pdam_org_id', $orgId)->exists()) {
            return;
        }
        $meters = DB::table('meters')->where('pdam_org_id', $orgId)->limit(20)->get();
        foreach ($meters as $meter) {
            DB::table('meter_lifecycle_events')->insert([
                'pdam_org_id' => $orgId, 'meter_id' => $meter->id, 'event' => 'installed',
                'from_status' => 'gudang', 'to_status' => 'terpasang', 'customer_id' => $meter->customer_id,
                'note' => 'Meter terpasang saat aktivasi pelanggan.', 'performed_by' => null,
                'created_at' => $meter->install_date ?? now()->subMonths(6), 'updated_at' => now(),
            ]);
        }
        $main = DB::table('warehouses')->where('pdam_org_id', $orgId)->where('warehouse_type', 'main')->first();
        $meterMat = DB::table('materials')->where('pdam_org_id', $orgId)->where('category', 'meter')->first();
        for ($i = 1; $i <= 20; $i++) {
            DB::table('meter_stock')->insert([
                'pdam_org_id' => $orgId, 'warehouse_id' => $main?->id, 'material_id' => $meterMat?->id,
                'brand' => 'Amico', 'diameter' => '0.5', 'status' => 'available', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $anomCust = DB::table('customers')->where('pdam_org_id', $orgId)->orderBy('id')->limit(3)->get();
        $rules = [
            ['spike', 'high', 20, 95, 'Lonjakan pemakaian tak wajar 4x lipat'],
            ['zero_streak', 'medium', 15, 0, 'Pemakaian nol 2 periode berturut'],
            ['night_flow', 'medium', 8, 25, 'Indikasi kebocoran (malam tidak turun)'],
        ];
        foreach ($anomCust as $i => $c) {
            $r = $rules[$i % count($rules)];
            $m = $meters->firstWhere('customer_id', $c->id);
            DB::table('meter_anomalies')->insert([
                'pdam_org_id' => $orgId, 'customer_id' => $c->id, 'meter_id' => $m?->id, 'period' => date('Y-m'),
                'rule_code' => $r[0], 'severity' => $r[1], 'expected_value' => $r[2], 'actual_value' => $r[3],
                'description' => $r[4], 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $target = $anomCust->first();
        if ($target) {
            DB::table('meter_replacements')->insert([
                'pdam_org_id' => $orgId, 'customer_id' => $target->id, 'old_serial' => $target->meter_serial_number,
                'new_serial' => 'MTR-NEW-'.$target->id, 'old_final_reading' => 120, 'new_initial_reading' => 0,
                'replaced_at' => now()->subDays(7)->toDateString(), 'reason' => 'Meter macet, hasil deteksi anomali',
                'processed_by' => null, 'created_at' => now()->subDays(7), 'updated_at' => now()->subDays(7),
            ]);
        }
    }

    private function seedAssets(int $orgId, $zone): void
    {
        if (DB::table('fixed_assets')->where('pdam_org_id', $orgId)->exists()) {
            return;
        }
        $cats = [
            ['TANAH', 'Tanah', 0, false, 0, '1-100', null, null],
            ['BANGUNAN', 'Bangunan & Gedung', 240, true, 0, '1-110', '1-190', '5-101'],
            ['MESIN', 'Mesin & Pompa', 120, true, 0, '1-120', '1-190', '5-101'],
            ['KENDARAAN', 'Kendaraan Dinas', 96, true, 0, '1-140', '1-190', '5-101'],
            ['JARINGAN', 'Jaringan Pipa Distribusi', 120, true, 0, '1-130', '1-190', '5-101'],
        ];
        $catIds = [];
        foreach ($cats as [$code, $name, $life, $dep, $rate, $assetAcc, $accumAcc, $expAcc]) {
            $catIds[$code] = DB::table('asset_categories')->insertGetId([
                'pdam_org_id' => $orgId, 'code' => $code, 'name' => $name, 'useful_life_months' => $life ?: 60,
                'depreciation_method' => 'straight_line', 'declining_rate' => $rate, 'is_depreciable' => $dep,
                'asset_account_code' => $assetAcc, 'accumulation_account_code' => $accumAcc, 'expense_account_code' => $expAcc,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $assets = [
            ['TANAH', 'AST-001', 'Tanah Kantor Pusat', '2025-09-01', 1500000000, 0],
            ['BANGUNAN', 'AST-002', 'Gedung Kantor PDAM Sambas', '2025-09-01', 1200000000, 5],
            ['MESIN', 'AST-003', 'Pompa Distribusi Sambas', '2025-09-01', 650000000, 8],
            ['JARINGAN', 'AST-004', 'Jaringan Pipa Utama Kota Sambas', '2025-09-01', 900000000, 10],
            ['KENDARAAN', 'AST-005', 'Truk Tangki Air Sambas', '2025-09-01', 420000000, 12],
        ];
        foreach ($assets as [$catCode, $code, $name, $acq, $cost, $useful]) {
            $monthlyDep = $useful > 0 ? round($cost / $useful, 2) : 0;
            $months = min(12, $useful ?: 1);
            $accum = $monthlyDep * $months;
            $assetId = DB::table('fixed_assets')->insertGetId([
                'pdam_org_id' => $orgId, 'asset_category_id' => $catIds[$catCode], 'zone_id' => $zone?->id,
                'code' => $code, 'name' => $name, 'acquisition_date' => $acq, 'acquisition_cost' => $cost,
                'residual_value' => 0, 'useful_life_months' => $useful ?: 60, 'depreciation_method' => 'straight_line',
                'declining_rate' => 0, 'is_depreciable' => $useful > 0, 'accumulated_depreciation' => $accum,
                'book_value' => $cost - $accum, 'source' => 'beli', 'status' => 'aktif',
                'last_depreciated_period' => $useful > 0 ? date('Y-m') : null, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($useful > 0 && $monthlyDep > 0) {
                foreach ($this->billingPeriods() as $period) {
                    DB::table('depreciation_entries')->insert([
                        'pdam_org_id' => $orgId, 'fixed_asset_id' => $assetId, 'period' => $period,
                        'depreciation_amount' => $monthlyDep, 'accumulated_after' => $accum, 'book_value_after' => $cost - $accum,
                        'journal_entry_id' => null, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    private function seedFinanceEnterprise(int $orgId): void
    {
        if (DB::table('currencies')->where('pdam_org_id', $orgId)->exists()) {
            return;
        }
        $cust = DB::table('customers')->where('pdam_org_id', $orgId)->first();
        $supplier = DB::table('suppliers')->where('pdam_org_id', $orgId)->first();

        foreach (['2025-10', '2026-03', '2026-06'] as $period) {
            DB::table('accounting_periods')->insert([
                'pdam_org_id' => $orgId, 'period' => $period, 'status' => 'open', 'closed_at' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('currencies')->insert([
            'pdam_org_id' => $orgId, 'code' => 'IDR', 'name' => 'Rupiah', 'symbol' => 'Rp', 'decimal_places' => 2,
            'is_default' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('exchange_rates')->insert([
            'pdam_org_id' => $orgId, 'from_currency' => 'USD', 'to_currency' => 'IDR', 'rate' => 16300.0,
            'effective_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $bankId = DB::table('bank_accounts')->insertGetId([
            'pdam_org_id' => $orgId, 'code' => 'BANK-01', 'account_name' => 'Rekening Operasional', 'account_number' => '4567890123',
            'bank_name' => 'Bank Kalbar', 'currency' => 'IDR', 'opening_balance' => 750000000, 'current_balance' => 720000000,
            'coa_account_code' => '1-001', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('bank_reconciliations')->insert([
            'pdam_org_id' => $orgId, 'bank_account_id' => $bankId, 'statement_date' => now()->subDays(5)->toDateString(),
            'reconciliation_date' => now()->subDays(3)->toDateString(), 'statement_balance' => 720500000,
            'book_balance' => 720000000, 'difference' => 500000, 'status' => 'draft', 'notes' => 'Selisih biaya admin bank belum dibukukan.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('cost_centers')->insert([
            'pdam_org_id' => $orgId, 'code' => 'CC-OPS-'.$orgId, 'name' => 'Operasional Distribusi Sambas',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('projects')->insert([
            'pdam_org_id' => $orgId, 'code' => 'PRJ-'.$orgId.'-01', 'name' => 'Perluasan Jaringan Zona Pemangkat',
            'status' => 'active', 'start_date' => now()->subMonth()->toDateString(), 'end_date' => now()->addMonths(6)->toDateString(),
            'budget' => 400000000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $budgetId = DB::table('budgets')->insertGetId([
            'pdam_org_id' => $orgId, 'name' => 'RKAP Sambas 2025/2026', 'fiscal_year' => 2026, 'total_amount' => 1200000000,
            'status' => 'approved', 'approved_at' => now()->subMonths(3), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('budget_lines')->insert([
            'pdam_org_id' => $orgId, 'budget_id' => $budgetId, 'account_code' => '5-001', 'project_code' => 'PRJ-'.$orgId.'-01',
            'cost_center_code' => 'CC-OPS-'.$orgId, 'amount' => 500000000, 'realized' => 150000000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $siId = DB::table('sales_invoices')->insertGetId([
            'pdam_org_id' => $orgId, 'customer_id' => $cust?->id, 'invoice_number' => 'SI-'.$orgId.'-0001',
            'invoice_date' => now()->subDays(20)->toDateString(), 'due_date' => now()->subDays(5)->toDateString(),
            'subtotal' => 2500000, 'discount' => 0, 'tax_amount' => 275000, 'total' => 2775000, 'paid_amount' => 2775000,
            'status' => 'paid', 'notes' => 'Biaya pemasangan sambungan baru.', 'created_at' => now()->subDays(20), 'updated_at' => now()->subDays(5),
        ]);
        DB::table('sales_invoice_items')->insert([
            'pdam_org_id' => $orgId, 'sales_invoice_id' => $siId, 'description' => 'Jasa pemasangan sambungan air',
            'quantity' => 1, 'unit_price' => 2500000, 'amount' => 2500000, 'account_code' => '4-002', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $piId = DB::table('purchase_invoices')->insertGetId([
            'pdam_org_id' => $orgId, 'supplier_id' => $supplier?->id, 'invoice_number' => 'PI-'.$orgId.'-0001',
            'supplier_invoice_number' => 'INV-SUP-8812', 'invoice_date' => now()->subDays(15)->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(), 'subtotal' => 30000000, 'tax_amount' => 3300000,
            'total' => 33300000, 'paid_amount' => 0, 'status' => 'unpaid', 'notes' => 'Pembelian tawas & pipa.',
            'created_at' => now()->subDays(15), 'updated_at' => now()->subDays(15),
        ]);
        DB::table('purchase_invoice_items')->insert([
            'pdam_org_id' => $orgId, 'purchase_invoice_id' => $piId, 'description' => 'Tawas & Pipa', 'quantity' => 250,
            'unit_price' => 120000, 'amount' => 30000000, 'account_code' => '1-003', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ar_ap_payments')->insert([
            'pdam_org_id' => $orgId, 'payment_type' => 'in', 'payable_type' => 'sales_invoice', 'payable_id' => $siId,
            'payment_number' => 'ARP-'.$orgId.'-0001', 'amount' => 2775000, 'payment_method' => 'transfer',
            'bank_account' => 'Bank Kalbar 4567890123', 'payment_date' => now()->subDays(5)->toDateString(),
            'notes' => 'Pelunasan invoice pemasangan.', 'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5),
        ]);
        DB::table('tax_records')->insert([
            'pdam_org_id' => $orgId, 'tax_type' => 'ppn', 'reference_type' => 'sales_invoice', 'reference_id' => $siId,
            'tax_number' => 'PPN-SMBS-0001', 'tax_date' => now()->subDays(20)->toDateString(), 'dpp' => 2500000,
            'tax_amount' => 275000, 'status' => 'paid', 'due_date' => now()->addDays(10)->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('recurring_transactions')->insert([
            'pdam_org_id' => $orgId, 'name' => 'Beban Listrik Pompa Bulanan', 'frequency' => 'monthly', 'amount' => 42000000,
            'journal_lines' => json_encode([
                ['account_code' => '5-002', 'type' => 'DEBIT', 'amount' => 42000000],
                ['account_code' => '1-001', 'type' => 'KREDIT', 'amount' => 42000000],
            ]),
            'next_run_date' => now()->addMonth()->startOfMonth()->toDateString(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }


    private function seedChemicals(int $orgId, $mainWarehouse): void
    {
        if (DB::table('chemicals')->where('pdam_org_id', $orgId)->exists()) {
            return;
        }
        $chemIds = [];
        foreach (self::CHEMICALS as [$code, $name, $unit, $dosage, $threshold]) {
            $chemIds[$code] = DB::table('chemicals')->insertGetId([
                'pdam_org_id' => $orgId, 'code' => $code, 'name' => $name, 'unit' => $unit,
                'standard_dosage' => $dosage, 'safety_threshold' => $threshold, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $supplierId = DB::table('chemical_suppliers')->insertGetId([
            'pdam_org_id' => $orgId, 'name' => 'CV Kimia Tirta Sambas', 'contact_name' => 'Bpk. Hendra',
            'phone' => '0561-733003', 'email' => 'sales@kimiatirta.co.id', 'address' => 'Sambas', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('chemical_purchase_requests')->insert([
            'pdam_org_id' => $orgId, 'pr_number' => 'CPR-'.$orgId.'-0001', 'chemical_id' => $chemIds['TAWAS'],
            'supplier_id' => $supplierId, 'quantity' => 2000, 'unit' => 'kg', 'required_date' => now()->addDays(14)->toDateString(),
            'status' => 'approved', 'approved_at' => now()->subDays(2), 'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(2),
        ]);
        $receiptId = DB::table('chemical_receipts')->insertGetId([
            'pdam_org_id' => $orgId, 'receipt_number' => 'CRC-'.$orgId.'-0001', 'supplier_id' => $supplierId,
            'receipt_date' => now()->subDays(3)->toDateString(), 'batch_number' => 'BATCH-TWAS-2609', 'expiry_date' => now()->addYear()->toDateString(),
            'status' => 'accepted', 'total_cost' => 24000000, 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3),
        ]);
        DB::table('chemical_receipt_items')->insert([
            'pdam_org_id' => $orgId, 'chemical_receipt_id' => $receiptId, 'chemical_id' => $chemIds['TAWAS'],
            'quantity' => 2000, 'unit' => 'kg', 'unit_cost' => 12000, 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3),
        ]);
        DB::table('chemical_qc_tests')->insert([
            'pdam_org_id' => $orgId, 'chemical_receipt_id' => $receiptId, 'test_name' => 'Kadar Al2O3', 'result_value' => 17.5,
            'result_unit' => '%', 'verdict' => 'pass', 'notes' => 'Memenuhi spesifikasi tawas.', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $stockId = DB::table('chemical_stocks')->insertGetId([
            'pdam_org_id' => $orgId, 'chemical_id' => $chemIds['TAWAS'], 'warehouse_id' => $mainWarehouse?->id,
            'batch_number' => 'BATCH-TWAS-2609', 'expiry_date' => now()->addYear()->toDateString(), 'quantity' => 1850,
            'unit' => 'kg', 'unit_cost' => 12000, 'created_at' => now()->subDays(3), 'updated_at' => now(),
        ]);
        DB::table('chemical_transactions')->insert([
            ['pdam_org_id' => $orgId, 'chemical_stock_id' => $stockId, 'type' => 'in', 'quantity' => 2000, 'balance_after' => 2000, 'reference_type' => 'receipt', 'reference_id' => $receiptId, 'notes' => 'Penerimaan tawas.', 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)],
            ['pdam_org_id' => $orgId, 'chemical_stock_id' => $stockId, 'type' => 'out', 'quantity' => 150, 'balance_after' => 1850, 'reference_type' => 'usage', 'reference_id' => null, 'notes' => 'Pemakaian dosing IPA.', 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()],
        ]);
        foreach ([2, 1, 0] as $d) {
            DB::table('chemical_usages')->insert([
                'pdam_org_id' => $orgId, 'chemical_id' => $chemIds['TAWAS'], 'usage_date' => now()->subDays($d)->toDateString(),
                'quantity' => 45 + $d, 'unit' => 'kg', 'water_produced_m3' => 9000 + $d * 200, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function seedFieldService(int $orgId, array $zones): void
    {
        if (DB::table('work_orders')->where('pdam_org_id', $orgId)->exists()) {
            return;
        }
        $technician = DB::table('users')->where('pdam_org_id', $orgId)->where('email', 'field_technician@gmail.com')->first();
        $cust = DB::table('customers')->where('pdam_org_id', $orgId)->limit(3)->get();
        $zone = $zones[0];
        $woDefs = [
            ['leak_repair', 'high', 'completed', 'Perbaikan kebocoran pipa dinas'],
            ['new_installation', 'medium', 'in_progress', 'Pemasangan sambungan baru'],
            ['meter_check', 'low', 'open', 'Pengecekan meter bermasalah'],
        ];
        foreach ($woDefs as $i => [$type, $priority, $status, $desc]) {
            $customer = $cust[$i] ?? $cust->first();
            $woId = DB::table('work_orders')->insertGetId([
                'pdam_org_id' => $orgId, 'wo_number' => sprintf('WO-%d-%04d', $orgId, $i + 1), 'type' => $type,
                'priority' => $priority, 'customer_id' => $customer?->id, 'zone_id' => $zone?->id, 'status' => $status,
                'sla_due_at' => now()->addDays(2), 'assigned_to' => $status !== 'open' ? $technician?->id : null,
                'address' => 'Lokasi pekerjaan '.($i + 1), 'description' => $desc,
                'started_at' => $status !== 'open' ? now()->subDays(2) : null, 'completed_at' => $status === 'completed' ? now()->subDay() : null,
                'resolution' => $status === 'completed' ? 'Pekerjaan selesai, pelanggan puas.' : null, 'customer_signature' => $status === 'completed',
                'created_at' => now()->subDays(3), 'updated_at' => now(),
            ]);
            $logSteps = [
                ['created', null, 'open', 3],
                ['started', 'open', 'in_progress', 2],
            ];
            if ($status === 'completed') {
                $logSteps[] = ['completed', 'in_progress', 'completed', 1];
            }
            foreach ($logSteps as $k => [$action, $from, $to, $daysAgo]) {
                DB::table('work_order_logs')->insert([
                    'pdam_org_id' => $orgId, 'work_order_id' => $woId, 'from_status' => $from, 'to_status' => $to,
                    'action' => $action, 'note' => ucfirst($action).' work order.', 'user_id' => $technician?->id,
                    'created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays($daysAgo),
                ]);
            }
        }
        if ($technician) {
            DB::table('technician_locations')->insert([
                'user_id' => $technician->id, 'latitude' => 1.35, 'longitude' => 109.30, 'accuracy' => 5.0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function seedMaintenance(int $orgId): void
    {
        if (DB::table('maintenance_schedules')->where('pdam_org_id', $orgId)->exists()) {
            return;
        }
        $technician = DB::table('users')->where('pdam_org_id', $orgId)->where('email', 'maintenance_technician@gmail.com')->first();
        $asset = DB::table('fixed_assets')->where('pdam_org_id', $orgId)->where('is_depreciable', true)->first();
        $schedules = [
            ['MNT-SMBS-001', 'Servis Rutin Pompa Distribusi', 'pump', 'monthly', 1],
            ['MNT-SMBS-002', 'Kalibrasi Meter Induk', 'meter', 'quarterly', 3],
            ['MNT-SMBS-003', 'Pembersihan Bak Filtrasi IPA', 'ipa', 'weekly', 1],
        ];
        foreach ($schedules as $i => [$code, $name, $assetType, $freq, $interval]) {
            $scheduleId = DB::table('maintenance_schedules')->insertGetId([
                'pdam_org_id' => $orgId, 'code' => $code, 'name' => $name, 'asset_type' => $assetType,
                'asset_id' => $i === 0 ? $asset?->id : null, 'frequency' => $freq, 'interval_value' => $interval,
                'next_due_date' => now()->addDays(7 * ($i + 1))->toDateString(), 'last_completed_date' => now()->subDays(7 * ($i + 1))->toDateString(),
                'is_active' => true, 'checklist_json' => json_encode(['Cek tekanan', 'Cek kebocoran', 'Ganti pelumas']),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('maintenance_records')->insert([
                'pdam_org_id' => $orgId, 'schedule_id' => $scheduleId, 'execution_date' => now()->subDays(7 * ($i + 1))->toDateString(),
                'technician_id' => $technician?->id, 'findings' => 'Kondisi normal, dilakukan perawatan rutin.',
                'cost_labor' => 250000, 'cost_material' => 150000, 'outcome' => 'completed', 'recommendations' => 'Lanjut jadwal berikutnya.',
                'journal_entry_id' => null, 'created_at' => now()->subDays(7 * ($i + 1)), 'updated_at' => now()->subDays(7 * ($i + 1)),
            ]);
        }
    }

    private function seedHr(int $orgId): void
    {
        if (DB::table('hr_employees')->where('pdam_org_id', $orgId)->exists()) {
            return;
        }
        $zone = DB::table('zones')->where('pdam_org_id', $orgId)->first();
        $positions = [
            ['DIR', 'Direktur', 1, 20000000, 35000000], ['KABAG', 'Kepala Bagian', 3, 10000000, 18000000], ['STAF', 'Staf', 5, 4000000, 8000000],
        ];
        $posIds = [];
        foreach ($positions as [$code, $name, $level, $min, $max]) {
            $posIds[$code] = DB::table('job_positions')->insertGetId([
                'pdam_org_id' => $orgId, 'code' => $code, 'name' => $name, 'structural_level' => $level,
                'min_salary' => $min, 'max_salary' => $max, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $unitId = DB::table('organization_units')->insertGetId([
            'pdam_org_id' => $orgId, 'code' => 'DIR-UT', 'name' => 'Direktorat Utama', 'zone_id' => $zone?->id,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $unitOps = DB::table('organization_units')->insertGetId([
            'pdam_org_id' => $orgId, 'code' => 'BAG-OPS', 'name' => 'Bagian Operasional', 'parent_id' => $unitId,
            'zone_id' => $zone?->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $gradeIds = [];
        foreach ([['IV', 'Golongan IV', 10000000, 18000000], ['II', 'Golongan II', 4000000, 8000000]] as [$c, $n, $min, $max]) {
            $gradeIds[$c] = DB::table('employee_grades')->insertGetId([
                'pdam_org_id' => $orgId, 'code' => $c, 'name' => $n, 'min_salary' => $min, 'max_salary' => $max, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $employees = [
            ['Slamet Riyadi', 'DIR', $unitId, 'IV', 'L', 'S2', 'tetap', 'K2', 2],
            ['Dewi Kartika', 'KABAG', $unitOps, 'IV', 'P', 'S1', 'tetap', 'K1', 1],
            ['Rudi Hartono', 'STAF', $unitOps, 'II', 'L', 'D3', 'kontrak', 'TK', 0],
        ];
        $empIds = [];
        foreach ($employees as $i => [$name, $pos, $unit, $grade, $gender, $edu, $empStatus, $taxStatus, $dependents]) {
            $empIds[$i] = DB::table('hr_employees')->insertGetId([
                'pdam_org_id' => $orgId, 'zone_id' => $zone?->id, 'position_id' => $posIds[$pos], 'unit_id' => $unit,
                'grade_id' => $gradeIds[$grade], 'nip' => sprintf('NIP%d%03d', $orgId, $i + 1), 'name' => $name,
                'gender' => $gender, 'birth_date' => '198'.$i.'-05-15', 'phone' => '0812900010'.$i,
                'email' => strtolower(str_replace(' ', '.', $name)).'@pdam.co.id', 'education' => $edu,
                'employment_status' => $empStatus, 'tax_status' => $taxStatus, 'dependents' => $dependents,
                'bank_name' => 'Bank Kalbar', 'bank_account' => '900'.$i.'11122', 'join_date' => '2021-0'.($i + 1).'-01',
                'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('organization_units')->where('id', $unitId)->update(['head_employee_id' => $empIds[0]]);
        DB::table('organization_units')->where('id', $unitOps)->update(['head_employee_id' => $empIds[1]]);
        $shiftId = DB::table('shifts')->insertGetId([
            'pdam_org_id' => $orgId, 'name' => 'Shift Pagi', 'start_time' => '08:00:00', 'end_time' => '16:00:00',
            'is_overnight' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([2, 1, 0] as $d) {
            DB::table('attendances')->insert([
                'pdam_org_id' => $orgId, 'employee_id' => $empIds[2], 'date' => now()->subDays($d)->toDateString(),
                'check_in' => '07:58:00', 'check_out' => '16:05:00', 'status' => 'present', 'source' => 'machine',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $leaveTypeId = DB::table('leave_types')->insertGetId([
            'pdam_org_id' => $orgId, 'code' => 'TAHUNAN', 'name' => 'Cuti Tahunan', 'default_quota' => 12,
            'is_paid' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('leaves')->insert([
            'pdam_org_id' => $orgId, 'employee_id' => $empIds[2], 'leave_type_id' => $leaveTypeId,
            'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(12)->toDateString(),
            'duration_days' => 3, 'balance_remaining' => 9, 'status' => 'approved', 'approved_by' => $empIds[1],
            'approved_at' => now(), 'reason' => 'Acara keluarga', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $trainingId = DB::table('trainings')->insertGetId([
            'pdam_org_id' => $orgId, 'title' => 'Pelatihan Manajemen NRW', 'start_date' => now()->subDays(20)->toDateString(),
            'end_date' => now()->subDays(18)->toDateString(), 'provider' => 'PERPAMSI', 'cost' => 5000000,
            'status' => 'completed', 'created_at' => now()->subDays(25), 'updated_at' => now()->subDays(18),
        ]);
        DB::table('training_participants')->insert([
            'training_id' => $trainingId, 'employee_id' => $empIds[1], 'attendance_status' => 'attended', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payroll_components')->insert([
            'pdam_org_id' => $orgId, 'code' => 'GAPOK', 'name' => 'Gaji Pokok', 'type' => 'earning', 'is_default' => true,
            'default_amount' => null, 'default_percent' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('employee_position_history')->insert([
            'employee_id' => $empIds[2], 'old_position_id' => null, 'new_position_id' => $posIds['STAF'],
            'new_unit_id' => $unitOps, 'effective_date' => '2021-03-01', 'type' => 'recruitment',
            'notes' => 'Penempatan awal.', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedDmsGisIntegration(int $orgId, $zone): void
    {
        if (DB::table('documents')->where('pdam_org_id', $orgId)->exists()) {
            return;
        }
        $user = DB::table('users')->where('pdam_org_id', $orgId)->first();
        $customer = DB::table('customers')->where('pdam_org_id', $orgId)->first();
        $complaint = DB::table('complaints')->where('pdam_org_id', $orgId)->first();

        $docId = DB::table('documents')->insertGetId([
            'pdam_org_id' => $orgId, 'doc_number' => 'DOC-'.$orgId.'-0001', 'title' => 'SOP Penanganan Kebocoran Pipa',
            'category' => 'sop', 'version' => 1, 'file_path' => 'documents/sop-kebocoran.pdf', 'file_type' => 'pdf',
            'file_size' => 524288, 'tags' => json_encode(['sop', 'teknik', 'kebocoran']), 'status' => 'approved',
            'uploaded_by' => $user?->id ?? 0, 'retention_until' => now()->addYears(5)->toDateString(),
            'created_at' => now()->subDays(30), 'updated_at' => now()->subDays(25),
        ]);
        DB::table('document_approvals')->insert([
            'document_id' => $docId, 'approver_id' => $user?->id ?? 0, 'approval_order' => 1, 'status' => 'approved',
            'approved_at' => now()->subDays(25), 'comments' => 'Disetujui untuk diberlakukan.', 'created_at' => now()->subDays(28), 'updated_at' => now()->subDays(25),
        ]);
        DB::table('call_logs')->insert([
            'pdam_org_id' => $orgId, 'call_id' => 'CALL-SMBS-0001', 'direction' => 'inbound', 'caller_number' => '081234567890',
            'callee_number' => '150XXX', 'start_time' => now()->subHours(3), 'end_time' => now()->subHours(3)->addMinutes(5),
            'duration_seconds' => 300, 'agent_id' => $user?->id, 'customer_id' => $customer?->id, 'complaint_id' => $complaint?->id,
            'disposition' => 'resolved', 'notes' => 'Pelanggan melaporkan air keruh, dibuatkan tiket.', 'status' => 'completed',
            'created_at' => now()->subHours(3), 'updated_at' => now()->subHours(3),
        ]);
        $pipeId = DB::table('gis_features')->insertGetId([
            'pdam_org_id' => $orgId, 'feature_type' => 'pipe', 'name' => 'Pipa Distribusi Utama Jl. Raya Sambas',
            'geometry' => json_encode(['type' => 'LineString', 'coordinates' => [[109.30, 1.35], [109.32, 1.36]]]),
            'properties' => json_encode(['diameter_mm' => 200, 'material' => 'HDPE']), 'zone_id' => $zone?->id,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $node1 = DB::table('gis_features')->insertGetId([
            'pdam_org_id' => $orgId, 'feature_type' => 'valve', 'name' => 'Valve SB-01', 'geometry' => json_encode(['type' => 'Point', 'coordinates' => [109.30, 1.35]]),
            'zone_id' => $zone?->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $node2 = DB::table('gis_features')->insertGetId([
            'pdam_org_id' => $orgId, 'feature_type' => 'junction', 'name' => 'Junction SB-02', 'geometry' => json_encode(['type' => 'Point', 'coordinates' => [109.32, 1.36]]),
            'zone_id' => $zone?->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('gis_network_edges')->insert([
            'pdam_org_id' => $orgId, 'pipe_feature_id' => $pipeId, 'from_node_id' => $node1, 'from_node_type' => 'valve',
            'to_node_id' => $node2, 'to_node_type' => 'junction', 'length_meters' => 320.50, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $integrationId = DB::table('integrations')->insertGetId([
            'pdam_org_id' => $orgId, 'name' => 'Payment Gateway', 'provider' => 'midtrans',
            'credentials' => json_encode(['server_key' => 'SB-Mid-server-xxxx']), 'config' => json_encode(['env' => 'sandbox']),
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('integration_logs')->insert([
            'pdam_org_id' => $orgId, 'integration_id' => $integrationId, 'action' => 'charge', 'status' => 'success',
            'http_status' => 200, 'request_payload' => json_encode(['order_id' => 'INV-001', 'amount' => 150000]),
            'response_payload' => json_encode(['transaction_status' => 'settlement']), 'retry_count' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('api_keys')->insert([
            'pdam_org_id' => $orgId, 'name' => 'Mobile App Key', 'key' => hash('sha256', Str::random(40)),
            'scopes' => json_encode(['read:bills', 'read:usage']), 'rate_limit' => 1000, 'expires_at' => now()->addYear(),
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }


    private function seedSmartUtility(int $orgId, $zone, $cust, $meter): void
    {
        if (DB::table('dma_zones')->where('pdam_org_id', $orgId)->exists()) {
            return;
        }
        $dmaId = DB::table('dma_zones')->insertGetId([
            'pdam_org_id' => $orgId, 'zone_id' => $zone?->id, 'code' => 'DMA-01', 'name' => 'DMA Sambas Kota',
            'total_connections' => $this->customerCount, 'base_demand_m3day' => 4800, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        for ($h = 5; $h >= 1; $h--) {
            DB::table('sensor_readings')->insert([
                'pdam_org_id' => $orgId, 'meter_id' => $meter?->id, 'customer_id' => $cust?->id, 'value' => 2200 + $h * 2,
                'flow_rate' => 0.85, 'pressure' => 2.4, 'battery' => 92.5, 'signal_strength' => 78.0,
                'reading_at' => now()->subHours($h), 'source' => 'lorawan', 'device_id' => 'AMI-SB-001', 'validated' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach ([now()->subDays(6), now()->subDays(3), now()->subDay()] as $d) {
            DB::table('production_logs')->insert([
                'pdam_org_id' => $orgId, 'production_date' => $d->toDateString(), 'raw_water_m3' => 21000,
                'treated_water_m3' => 19800, 'distributed_water_m3' => 19200, 'pump_runtime_hours' => 22.5,
                'power_consumption_kwh' => 4800, 'turbidity_ntu' => 1.2, 'ph' => 7.1, 'chlorine_residual' => 0.6,
                'status' => 'normal', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        for ($h = 3; $h >= 1; $h--) {
            DB::table('distribution_readings')->insert([
                'pdam_org_id' => $orgId, 'dma_zone_id' => $dmaId, 'reading_at' => now()->subHours($h),
                'flow_rate_m3h' => 150.5, 'pressure_bar' => 2.5, 'reservoir_level_percent' => 78.0, 'chlorine_residual' => 0.55,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $systemInput = 145000; $billed = 118000; $unbilledMetered = 3000; $unbilledUnmetered = 2500;
        $authorised = $billed + $unbilledMetered + $unbilledUnmetered;
        $losses = $systemInput - $authorised; $apparent = 9000; $real = $losses - $apparent;
        DB::table('nrw_balances')->insert([
            'pdam_org_id' => $orgId, 'dma_zone_id' => $dmaId, 'period' => date('Y-m'),
            'system_input_m3' => $systemInput, 'billed_metered_m3' => $billed, 'unbilled_metered_m3' => $unbilledMetered,
            'unbilled_unmetered_m3' => $unbilledUnmetered, 'authorised_consumption_m3' => $authorised,
            'water_losses_m3' => $losses, 'apparent_losses_m3' => $apparent, 'real_losses_m3' => $real,
            'nrw_percentage' => round($losses / $systemInput * 100, 2), 'ili' => 2.1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ml_predictions')->insert([
            ['pdam_org_id' => $orgId, 'model_name' => 'demand_forecast_v1', 'prediction_type' => 'demand', 'entity_type' => 'dma_zone', 'entity_id' => $dmaId, 'period' => date('Y-m', strtotime('+1 month')), 'predicted_value' => 150000, 'confidence_min' => 142000, 'confidence_max' => 158000, 'features' => json_encode(['seasonality' => 'dry', 'trend' => 'up']), 'status' => 'predicted', 'created_at' => now(), 'updated_at' => now()],
            ['pdam_org_id' => $orgId, 'model_name' => 'leak_detection_v1', 'prediction_type' => 'anomaly', 'entity_type' => 'dma_zone', 'entity_id' => $dmaId, 'period' => date('Y-m'), 'predicted_value' => 1, 'confidence_min' => 0.72, 'confidence_max' => 0.95, 'features' => json_encode(['night_flow_high' => true]), 'status' => 'predicted', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function seedBillingExtra(int $orgId): void
    {
        if (DB::table('installment_plans')->where('pdam_org_id', $orgId)->exists()) {
            return;
        }
        // Ambil pelanggan dengan tagihan menunggak untuk dibuat cicilan.
        $arrears = DB::table('bills')->where('pdam_org_id', $orgId)->where('status', 'unpaid')->orderBy('customer_id')->get()->groupBy('customer_id');
        $groupId = 0;
        foreach ($arrears->take(2) as $customerId => $bills) {
            $totalAmount = (float) $bills->sum('amount_due');
            if ($totalAmount <= 0) {
                continue;
            }
            $tenor = 3;
            $monthly = round($totalAmount / $tenor, 2);
            $planId = DB::table('installment_plans')->insertGetId([
                'pdam_org_id' => $orgId, 'customer_id' => $customerId,
                'plan_number' => sprintf('INST-%d-%04d', $orgId, ++$groupId), 'total_amount' => $totalAmount,
                'tenor_months' => $tenor, 'monthly_amount' => $monthly, 'status' => 'active',
                'approved_at' => now()->subDays(15), 'created_at' => now()->subDays(16), 'updated_at' => now()->subDays(15),
            ]);
            foreach ($bills as $bill) {
                DB::table('installment_plan_bills')->insert([
                    'pdam_org_id' => $orgId, 'plan_id' => $planId, 'bill_id' => $bill->id,
                    'amount' => $bill->amount_due, 'created_at' => now()->subDays(15), 'updated_at' => now()->subDays(15),
                ]);
            }
            for ($n = 1; $n <= $tenor; $n++) {
                DB::table('installment_schedules')->insert([
                    'pdam_org_id' => $orgId, 'plan_id' => $planId, 'installment_no' => $n, 'amount' => $monthly,
                    'due_date' => now()->addMonths($n - 1)->toDateString(), 'status' => $n === 1 ? 'paid' : 'unpaid',
                    'paid_at' => $n === 1 ? now()->subDays(10) : null, 'created_at' => now()->subDays(15), 'updated_at' => now()->subDays(15),
                ]);
            }
        }
        $sampleBill = DB::table('bills')->where('pdam_org_id', $orgId)->first();
        $requester = DB::table('users')->where('pdam_org_id', $orgId)->first();
        if ($sampleBill && $requester) {
            $old = (float) $sampleBill->amount_due;
            $new = round($old * 0.9, 2);
            DB::table('bill_adjustments')->insert([
                'pdam_org_id' => $orgId, 'bill_id' => $sampleBill->id, 'type' => 'correction',
                'reason' => 'Koreksi kesalahan baca meter', 'old_amount' => $old, 'new_amount' => $new,
                'adjustment_amount' => $new - $old, 'requested_by' => $requester->id, 'approved_by' => $requester->id,
                'status' => 'approved', 'notes' => 'Disetujui setelah verifikasi ulang angka meter.',
                'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5),
            ]);
        }
    }

    private function seedCrm(int $orgId): void
    {
        if (DB::table('complaints')->where('pdam_org_id', $orgId)->exists()) {
            return;
        }
        $cs = DB::table('users')->where('pdam_org_id', $orgId)->where('email', 'customer_service@gmail.com')->first();
        $customers = DB::table('customers')->where('pdam_org_id', $orgId)->limit(3)->get();
        $samples = [
            ['kebocoran', 'high', 'Pipa bocor di depan rumah', 'Air merembes dari sambungan pipa depan rumah.', 'resolved'],
            ['tagihan', 'medium', 'Tagihan bulan ini terlalu tinggi', 'Mohon dicek, tagihan naik drastis padahal pemakaian normal.', 'in_progress'],
            ['air_mati', 'high', 'Air tidak mengalir sejak 2 hari', 'Sudah 2 hari air tidak keluar sama sekali di wilayah kami.', 'open'],
        ];
        foreach ($customers as $i => $customer) {
            [$category, $priority, $subject, $desc, $status] = $samples[$i % count($samples)];
            $complaintId = DB::table('complaints')->insertGetId([
                'pdam_org_id' => $orgId, 'customer_id' => $customer->id,
                'ticket_number' => sprintf('TKT-SMBS-%04d', $i + 1), 'category' => $category, 'priority' => $priority,
                'subject' => $subject, 'description' => $desc, 'status' => $status,
                'sla_due_at' => now()->addDays(2), 'resolved_at' => $status === 'resolved' ? now()->subDay() : null,
                'resolution' => $status === 'resolved' ? 'Sudah diperbaiki teknisi, air kembali normal.' : null,
                'created_at' => now()->subDays(3 - $i), 'updated_at' => now(),
            ]);
            DB::table('complaint_tracks')->insert([
                'pdam_org_id' => $orgId, 'complaint_id' => $complaintId, 'from_status' => null, 'to_status' => 'open',
                'action' => 'created', 'note' => 'Tiket pengaduan dibuat oleh pelanggan.', 'user_id' => $cs?->id,
                'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3),
            ]);
            if (in_array($status, ['in_progress', 'resolved'], true)) {
                DB::table('complaint_tracks')->insert([
                    'pdam_org_id' => $orgId, 'complaint_id' => $complaintId, 'from_status' => 'open', 'to_status' => 'in_progress',
                    'action' => 'assigned', 'note' => 'Tiket ditugaskan ke petugas lapangan.', 'user_id' => $cs?->id,
                    'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
                ]);
            }
            if ($status === 'resolved') {
                DB::table('complaint_tracks')->insert([
                    'pdam_org_id' => $orgId, 'complaint_id' => $complaintId, 'from_status' => 'in_progress', 'to_status' => 'resolved',
                    'action' => 'resolved', 'note' => 'Masalah selesai ditangani teknisi.', 'user_id' => $cs?->id,
                    'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
                ]);
                DB::table('customer_feedbacks')->insert([
                    'pdam_org_id' => $orgId, 'customer_id' => $customer->id, 'complaint_id' => $complaintId,
                    'rating' => 5, 'comment' => 'Penanganan cepat, terima kasih petugas PDAM.', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    private function seedSurvey(int $orgId, array $zones): void
    {
        if (DB::table('customer_prospects')->where('pdam_org_id', $orgId)->exists()) {
            return;
        }
        $zone = $zones[0];
        $tariff = DB::table('tariff_categories')->where('pdam_org_id', $orgId)->first();
        $prospects = [
            ['Andi Pratama', 'survey_approved', 'feasible', 'approved'],
            ['Maria Ulfa', 'surveying', null, null],
            ['Joko Widodo', 'installation_scheduled', 'feasible', 'approved'],
            ['Dedi Setiawan', 'installed', 'feasible', 'approved'],
        ];
        foreach ($prospects as $i => [$name, $status, $recommendation, $reviewStatus]) {
            $prospectId = DB::table('customer_prospects')->insertGetId([
                'pdam_org_id' => $orgId, 'registration_number' => sprintf('REG-SMBS-%04d', $i + 1), 'nik' => null,
                'full_name' => $name, 'birth_place' => 'Sambas', 'birth_date' => '1990-01-0'.($i + 1),
                'address' => 'Alamat KTP '.$name, 'installation_address' => 'Alamat pemasangan '.$name,
                'zone_id' => $zone?->id, 'phone' => '08123456700'.$i, 'tariff_category_id' => $tariff?->id,
                'status' => $status, 'installation_fee' => 1500000, 'created_at' => now()->subDays(10 - $i), 'updated_at' => now()->subDays(5 - $i),
            ]);
            if ($recommendation !== null) {
                DB::table('survey_reports')->insert([
                    'pdam_org_id' => $orgId, 'prospect_id' => $prospectId, 'surveyor_id' => 0,
                    'distance_to_main_pipe' => 12.5 + $i, 'building_condition' => 'permanen', 'accessibility' => 'mudah',
                    'estimated_cost' => 1500000, 'recommendation' => $recommendation, 'review_status' => $reviewStatus,
                    'review_notes' => 'Layak dipasang.', 'reviewed_at' => now()->subDays(3), 'created_at' => now()->subDays(4), 'updated_at' => now()->subDays(3),
                ]);
            }
            if ($status === 'installation_scheduled') {
                DB::table('installation_schedules')->insert([
                    'pdam_org_id' => $orgId, 'prospect_id' => $prospectId, 'scheduled_date' => now()->addDays(3)->toDateString(),
                    'status' => 'scheduled', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        $lastCustomer = DB::table('customers')->where('pdam_org_id', $orgId)->orderByDesc('id')->first();
        if ($lastCustomer) {
            DB::table('ownership_transfers')->insert([
                'pdam_org_id' => $orgId, 'customer_id' => $lastCustomer->id, 'old_owner_name' => $lastCustomer->full_name,
                'new_owner_name' => 'Pemilik Baru Sdr. Rahmat', 'new_owner_phone' => '081299998888',
                'effective_date' => now()->subDays(5)->toDateString(), 'processed_by' => null,
                'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5),
            ]);
        }
    }

    private function seedNotifications(int $orgId, $cust): void
    {
        if (DB::table('app_notifications')->where('pdam_org_id', $orgId)->exists()) {
            return;
        }
        $user = DB::table('users')->where('pdam_org_id', $orgId)->first();
        $notifs = [
            ['bill_reminder', 'Tagihan Bulan Ini', 'Tagihan air periode '.(($this->billingPeriods()[count($this->billingPeriods()) - 1] ?? '2026-06')).' sudah terbit. Segera lakukan pembayaran.'],
            ['overdue', 'Tagihan Menunggak', 'Terdapat tagihan yang melewati jatuh tempo. Mohon segera dilunasi.'],
            ['info', 'Pemeliharaan Jaringan', 'Akan ada pemeliharaan jaringan di zona Anda pada akhir pekan ini.'],
        ];
        foreach ($notifs as $i => [$type, $title, $body]) {
            $notifId = DB::table('app_notifications')->insertGetId([
                'pdam_org_id' => $orgId, 'user_id' => $user?->id, 'type' => $type, 'title' => $title, 'body' => $body,
                'data' => json_encode(['source' => 'seeder']), 'channel' => 'in_app', 'read_at' => $i === 0 ? now() : null,
                'created_at' => now()->subDays(3 - $i), 'updated_at' => now()->subDays(3 - $i),
            ]);
            DB::table('notification_logs')->insert([
                'pdam_org_id' => $orgId, 'notification_id' => $notifId, 'channel' => 'in_app', 'status' => 'sent',
                'error_message' => null, 'created_at' => now()->subDays(3 - $i), 'updated_at' => now()->subDays(3 - $i),
            ]);
        }
        if ($user && $cust) {
            $chatId = DB::table('chats')->insertGetId([
                'pdam_org_id' => $orgId, 'customer_id' => $cust->id, 'user_id' => $user->id, 'assigned_to' => $user->id,
                'subject' => 'Pertanyaan tagihan', 'status' => 'open', 'created_at' => now()->subDay(), 'updated_at' => now(),
            ]);
            DB::table('chat_messages')->insert([
                ['chat_id' => $chatId, 'sender_id' => $cust->id, 'sender_type' => 'customer', 'message' => 'Selamat siang, saya mau tanya soal tagihan bulan ini.', 'is_read' => true, 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()],
                ['chat_id' => $chatId, 'sender_id' => $user->id, 'sender_type' => 'user', 'message' => 'Selamat siang, silakan sebutkan nomor pelanggan Anda.', 'is_read' => false, 'created_at' => now()->subHours(20), 'updated_at' => now()->subHours(20)],
            ]);
        }
    }
}
