<?php

namespace Database\Seeders;

use App\Models\PdamOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * AddressSeeder — master alamat berjenjang (provinces → cities → districts → villages → streets).
 *
 * Data alamat bersifat GLOBAL (province/city/district/village), tapi `streets` diberi
 * pdam_org_id agar tiap tenant punya jalan lokalnya sendiri dan bisa disambungkan ke
 * customers.street_id + meter_route_streets.
 *
 * Sumber: Pontianak (Kalbar) untuk PDAM Canada, Surabaya (Jatim) untuk PDAM Brazil.
 * Idempotent: dilewati bila streets sudah ada.
 */
class AddressSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('streets')->exists()) {
            return;
        }

        // ── Provinsi ──
        $kalbarId = $this->province('61', 'Kalimantan Barat');
        $jatimId = $this->province('35', 'Jawa Timur');

        // ── Kota ──
        $pontianakId = $this->city($kalbarId, '6171', 'Pontianak', 'kota');
        $surabayaId = $this->city($jatimId, '3578', 'Surabaya', 'kota');

        // ── Kecamatan + Kelurahan + Jalan untuk Pontianak (Canada) ──
        $pontianakData = [
            ['617101', 'Pontianak Kota', [
                ['6171011', 'Sungai Bangkong', '78116', ['Jl. Gajah Mada', 'Jl. Veteran', 'Jl. Sultan Abdurrahman']],
                ['6171012', 'Darat Sekip', '78117', ['Jl. Ahmad Yani', 'Jl. Teuku Umar']],
            ]],
            ['617102', 'Pontianak Utara', [
                ['6171021', 'Siantan Hulu', '78241', ['Jl. Khatulistiwa', 'Jl. 28 Oktober']],
                ['6171022', 'Batu Layang', '78242', ['Jl. Selat Panjang']],
            ]],
            ['617103', 'Pontianak Timur', [
                ['6171031', 'Saigon', '78231', ['Jl. Panglima Aim', 'Jl. Tanjung Raya 2']],
            ]],
            ['617104', 'Pontianak Selatan', [
                ['6171041', 'Akcaya', '78121', ['Jl. Sutoyo', 'Jl. KH Wahid Hasyim']],
            ]],
        ];

        // ── Kecamatan + Kelurahan + Jalan untuk Surabaya (Brazil) ──
        $surabayaData = [
            ['357801', 'Surabaya Pusat', [
                ['3578011', 'Genteng', '60275', ['Jl. Basuki Rahmat', 'Jl. Embong Malang']],
                ['3578012', 'Tegalsari', '60262', ['Jl. Wonokromo', 'Jl. Raya Darmo']],
            ]],
            ['357802', 'Surabaya Timur', [
                ['3578021', 'Gubeng', '60281', ['Jl. Raya Gubeng', 'Jl. Pemuda']],
            ]],
        ];

        $canada = PdamOrganization::where('code', 'pdam-canada')->first();
        $brazil = PdamOrganization::where('code', 'pdam-brazil')->first();

        if ($canada) {
            $this->seedTreeAndStreets($pontianakId, $pontianakData, $canada->id);
        }
        if ($brazil) {
            $this->seedTreeAndStreets($surabayaId, $surabayaData, $brazil->id);
        }

        // Sambungkan customer.street_id ke street pada zona yang cocok (best-effort)
        $this->linkCustomersToStreets($canada?->id);
        $this->linkCustomersToStreets($brazil?->id);

        // Isi meter_route_streets & meter_route_assignments per tenant
        $this->seedRouteStreets($canada?->id);
        $this->seedRouteStreets($brazil?->id);
        $this->seedRouteAssignments($canada?->id);
        $this->seedRouteAssignments($brazil?->id);
    }

    private function province(string $code, string $name): int
    {
        return DB::table('provinces')->insertGetId([
            'code' => $code, 'name' => $name,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function city(int $provinceId, string $code, string $name, string $type): int
    {
        return DB::table('cities')->insertGetId([
            'province_id' => $provinceId, 'code' => $code, 'name' => $name, 'type' => $type,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedTreeAndStreets(int $cityId, array $districts, int $orgId): void
    {
        foreach ($districts as [$dCode, $dName, $villages]) {
            $districtId = DB::table('districts')->insertGetId([
                'city_id' => $cityId, 'code' => $dCode, 'name' => $dName,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach ($villages as [$vCode, $vName, $postal, $streets]) {
                $villageId = DB::table('villages')->insertGetId([
                    'district_id' => $districtId, 'code' => $vCode, 'name' => $vName,
                    'postal_code' => $postal, 'created_at' => now(), 'updated_at' => now(),
                ]);

                foreach ($streets as $streetName) {
                    DB::table('streets')->insert([
                        'pdam_org_id' => $orgId, 'village_id' => $villageId,
                        'name' => $streetName, 'is_active' => true,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    private function linkCustomersToStreets(?int $orgId): void
    {
        if (! $orgId) {
            return;
        }

        $streetIds = DB::table('streets')->where('pdam_org_id', $orgId)->pluck('id')->all();
        if (empty($streetIds)) {
            return;
        }

        $customers = DB::table('customers')->where('pdam_org_id', $orgId)->get();
        foreach ($customers as $i => $customer) {
            DB::table('customers')->where('id', $customer->id)->update([
                'street_id' => $streetIds[$i % count($streetIds)],
                'address_detail' => 'No. ' . ($i + 1) . ', RT 00' . (($i % 5) + 1) . '/RW 00' . (($i % 3) + 1),
            ]);
        }
    }

    private function seedRouteStreets(?int $orgId): void
    {
        if (! $orgId) {
            return;
        }

        $routes = DB::table('meter_routes')->where('pdam_org_id', $orgId)->get();
        $streetIds = DB::table('streets')->where('pdam_org_id', $orgId)->pluck('id')->all();
        if (empty($streetIds) || $routes->isEmpty()) {
            return;
        }

        // Bagi jalan ke rute secara round-robin
        foreach ($streetIds as $i => $streetId) {
            $route = $routes[$i % $routes->count()];
            DB::table('meter_route_streets')->insertOrIgnore([
                'pdam_org_id' => $orgId,
                'meter_route_id' => $route->id,
                'street_id' => $streetId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function seedRouteAssignments(?int $orgId): void
    {
        if (! $orgId) {
            return;
        }

        $routes = DB::table('meter_routes')->where('pdam_org_id', $orgId)->get();
        // Ambil user petugas baca meter di tenant ini (fallback: user mana pun)
        $officer = DB::table('users')->where('pdam_org_id', $orgId)
            ->where('email', 'meter_reader@gmail.com')->first()
            ?? DB::table('users')->where('pdam_org_id', $orgId)->first();

        if (! $officer || $routes->isEmpty()) {
            return;
        }

        foreach ($routes as $route) {
            DB::table('meter_route_assignments')->insert([
                'pdam_org_id' => $orgId,
                'meter_route_id' => $route->id,
                'officer_id' => $officer->id,
                'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
