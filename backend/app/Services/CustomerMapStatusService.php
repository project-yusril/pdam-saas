<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Collection;

/**
 * CustomerMapStatusService — warna titik rumah pelanggan di peta (OSM/Leaflet).
 *
 * Legenda (kesepakatan dashboard direktur):
 *  green  = lunas (tanpa tagihan unpaid/overdue)
 *  blue   = ada tagihan, tapi belum lewat jatuh tempo
 *  yellow = menunggak 1 periode
 *  orange = menunggak 2 periode
 *  red    = menunggak >=3 periode
 *  black  = sambungan putus (isolir/terminated/disconnected)
 *
 * Sumber kebenaran putus = Customer.status (customer_status_history mencatat
 * reconnect, jadi tidak perlu query tabel disconnections).
 */
class CustomerMapStatusService
{
    /** Status pelanggan yang dianggap "putus" (hitam di peta). */
    public const DISCONNECTED_STATUSES = ['isolir', 'terminated', 'disconnected'];

    /** Status yang tidak ditampilkan di peta. */
    public const EXCLUDED_STATUSES = ['inactive', 'temporary_closed'];

    public const COLORS = [
        'green' => 'Lunas',
        'blue' => 'Belum jatuh tempo',
        'yellow' => 'Menunggak 1 bulan',
        'orange' => 'Menunggak 2 bulan',
        'red' => 'Menunggak 3+ bulan',
        'black' => 'Putus / Isolir',
    ];

    private const HEX = [
        'green' => '#22c55e',
        'blue' => '#38bdf8',
        'yellow' => '#eab308',
        'orange' => '#f97316',
        'red' => '#ef4444',
        'black' => '#1e293b',
    ];

    /**
     * Klasifikasi batch — total 1 query agregate (chunk 500 id) untuk semua
     * pelanggan, bukan 4 query per pelanggan.
     *
     * @return array<int, array{status_color:string,status_label:string,marker_color:string,arrears_months:int,total_arrears:float,open_bills:int}>
     */
    public function classifyBatch(Collection $customers): array
    {
        $result = [];
        foreach ($customers->chunk(500) as $chunk) {
            $agg = $this->aggregateBills($chunk->pluck('id')->all());

            foreach ($chunk as $customer) {
                $result[$customer->id] = $this->classifyOne($customer, $agg[$customer->id] ?? null);
            }
        }

        return $result;
    }

    public function classify(Customer $customer): array
    {
        return $this->classifyOne($customer, $this->aggregateBills([$customer->id])[$customer->id] ?? null);
    }

    /** Ringkasan jumlah per warna (untuk legend/dashboard). */
    public function statusCounts(Collection $customers): array
    {
        $counts = array_fill_keys(array_keys(self::COLORS), 0);
        foreach ($this->classifyBatch($customers) as $row) {
            $counts[$row['status_color']]++;
        }

        return $counts;
    }

    /**
     * FeatureCollection GeoJSON siap render Leaflet.
     * Nama zona diambil 1 query tambahan (anti-N+1).
     */
    public function featureCollection(Collection $customers): array
    {
        $rows = $this->classifyBatch($customers);
        $zoneNames = Zone::whereIn('id', $customers->pluck('zone_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        $features = $customers
            ->filter(fn (Customer $c) => $c->latitude !== null && $c->longitude !== null)
            ->map(function (Customer $c) use ($rows, $zoneNames) {
                $f = $this->buildGeoJsonFeature($c, $rows[$c->id] ?? null);
                $f['properties']['zone'] = $zoneNames[$c->zone_id] ?? null;

                return $f;
            })
            ->values()
            ->all();

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    public function buildGeoJsonFeature(Customer $customer, ?array $row = null): array
    {
        $row ??= $this->classify($customer);
        $color = $row['status_color'];

        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [(float) $customer->longitude, (float) $customer->latitude],
            ],
            'properties' => [
                'id' => $customer->id,
                'customer_number' => $customer->customer_number,
                'name' => $customer->full_name,
                'address' => $customer->address_detail,
                'phone' => $customer->phone,
                'zone_id' => $customer->zone_id,
                'meter_route_id' => $customer->meter_route_id,
                'status_color' => $color,
                'status_label' => $row['status_label'],
                'marker_color' => $row['marker_color'],
                'icon' => 'house-'.$color,
                'arrears_months' => $row['arrears_months'],
                'total_arrears' => $row['total_arrears'],
                'customer_status' => $customer->status,
            ],
        ];
    }

    // ── API lama (tetap dipakai call-site kecil / kompatibel) ──────────────

    public function getStatusColor(Customer $customer): string
    {
        return $this->classify($customer)['status_color'];
    }

    public function getArrearsMonths(Customer $customer): int
    {
        return $this->classify($customer)['arrears_months'];
    }

    public function getTotalArrears(Customer $customer): float
    {
        return $this->classify($customer)['total_arrears'];
    }

    // ── internal ───────────────────────────────────────────────────────────

    /** @param  array<int>  $ids */
    private function aggregateBills(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $today = now()->toDateString();

        return Bill::query()
            ->whereIn('customer_id', $ids)
            ->whereIn('status', ['unpaid', 'overdue'])
            ->selectRaw(
                'customer_id,
                 COUNT(DISTINCT CASE WHEN due_date < ? THEN period END) AS arrears_periods,
                 COUNT(*) AS open_bills,
                 COALESCE(SUM(amount_due), 0) AS total_arrears',
                [$today]
            )
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id')
            ->all();
    }

    private function classifyOne(Customer $customer, ?object $agg): array
    {
        if (in_array($customer->status, self::DISCONNECTED_STATUSES, true)) {
            $color = 'black';
        } elseif ($agg === null) {
            $color = 'green';
        } elseif ((int) $agg->arrears_periods >= 3) {
            $color = 'red';
        } elseif ((int) $agg->arrears_periods === 2) {
            $color = 'orange';
        } elseif ((int) $agg->arrears_periods === 1) {
            $color = 'yellow';
        } else {
            $color = 'blue';
        }

        return [
            'status_color' => $color,
            'status_label' => self::COLORS[$color],
            'marker_color' => self::HEX[$color],
            'arrears_months' => $agg ? (int) $agg->arrears_periods : 0,
            'total_arrears' => $agg ? round((float) $agg->total_arrears, 2) : 0.0,
            'open_bills' => $agg ? (int) $agg->open_bills : 0,
        ];
    }
}
