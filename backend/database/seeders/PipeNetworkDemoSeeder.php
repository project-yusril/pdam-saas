<?php

namespace Database\Seeders;

use App\Services\Geo\NetworkGraphService;
use App\Support\TenantContext;
use Carbon\Carbon;
use Database\Seeders\Support\DemoGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * PipeNetworkDemoSeeder — GIS Jaringan Perpipaan DEMO yang KONSISTEN dgn data
 * nyata: SELURUH jaringan (trunk, distribusi, sambungan rumah, valve, DMA)
 * diturunkan dari koordinat pelanggan ASLI per rute baca meter, sehingga di
 * peta setiap rumah benar-benar tersambung pipa — seperti jaringan PDAM asli.
 *
 * Untuk tiap zona (Sambas: 7 zona, tiap zona 1 rute RT-0N; pelanggannya sudah
 * diklaster oleh SambasTenantSeeder di sekitar centroid kecamatannya):
 *   1. node VALVE di centroid klaster (titik cabang trunk→distribusi);
 *   2. tiap pelanggan = node 'tap' SMBS-TAP di koordinat rumahnya PERSIS;
 *   3. MST (Prim) menyambung SEMUA tap ke valve zona → pipa SR Ø20 PE per
 *      rumah, Ø75/110 utk ruas cabang; percabangan ≥3 anak = valve hub
 *      (agar isolasi bocor bisa sepemilik-jalan, seperti realnya);
 *   4. pipa trunk DI Ø400 menyambung pompa → tiap valve zona → reservoir;
 *   5. dma_zones.boundary = convex hull rumah ter-buffer (dipakai hitung NRW).
 *
 * Idempotent: kalau fitur MARKER utk organisasi itu sudah ada → lewati
 * (gunakan purge script untuk rebuild).
 */
class PipeNetworkDemoSeeder extends Seeder
{
    private const MARKER = 'SMBS-NET';

    private int $orgId = 0;

    private \Illuminate\Support\Carbon $now;

    private NetworkGraphService $geo;

    /** kueri "feature_type" per node id utk edge dari node. */
    private array $typeByNodeId = [];

    public function run(): void
    {
        DemoGuard::assertNonProduction('PipeNetworkDemoSeeder');
        foreach (DB::table('pdam_organizations')->whereIn('code', ['pdam-sambas'])->pluck('id')->all() as $orgId) {
            $this->seedForTenant((int) $orgId);
        }
    }

    private function seedForTenant(int $orgId): void
    {
        $this->orgId = $orgId;
        $this->now = now();
        $this->geo = app(NetworkGraphService::class);
        $this->typeByNodeId = [];

        if (DB::table('gis_features')->where('pdam_org_id', $orgId)->where('name', 'like', self::MARKER.'%')->exists()) {
            $this->command?->line('[NET] '.self::MARKER.' org '.$orgId.' sudah ada — lewati.');

            return;
        }

        TenantContext::set($orgId);
        $clusters = $this->clustersFromCustomers();
        if (count($clusters) < 2) {
            $this->command?->warn('[NET] butuh ≥2 klaster rute ber-koordinat (seed pelanggan dulu) — lewati.');
            TenantContext::clear();

            return;
        }

        $edges = []; // [pipe_id, from_node_id, to_node_id]

        // ── 1. Jaringan per rute: valve cabang + MST sambung-ke-setiap-rumah ─
        foreach ($clusters as $i => $c) {
            $valve = $this->node('valve', $c['centroid'], self::MARKER." Valve {$c['zone_code']} (rute ".($i + 1).')', $c['zone_id']);
            $clusters[$i]['valve'] = $valve;
            array_push($edges, ...$this->buildZoneNetwork($c, $valve));
        }

        // ── 2. trunk pompa → seluruh valve centroid → reservoir ────────────
        $west = $clusters[0]['centroid'];
        $east = $clusters[count($clusters) - 1]['centroid'];
        $pumpPos = [$west[0] - 0.020, $west[1] - 0.010];
        $resPos = [$east[0] + 0.020, $east[1] + 0.010];
        $prevNode = $this->node('pump', $pumpPos, self::MARKER.' Pompa IPA Sambas', $clusters[0]['zone_id']);
        $prevCoord = $this->rounded($pumpPos);

        foreach ($clusters as $i => $c) {
            $curCoord = $this->rounded($c['centroid']);
            $mid = [round(($prevCoord[0] + $curCoord[0]) / 2, 7), round(($prevCoord[1] + $curCoord[1]) / 2 + 0.006, 7)];
            $pipe = $this->pipe([$prevCoord, $mid, $curCoord], self::MARKER.' Pipa Trunk '.($i + 1), $c['zone_id'], 400, 'DI');
            $edges[] = [$pipe, $prevNode, $c['valve']];
            $prevNode = $c['valve'];
            $prevCoord = $curCoord;
        }
        $reservoir = $this->node('reservoir', $resPos, self::MARKER.' Reservoir Tandon Sambas', $c['zone_id'] ?? null);
        $pipe = $this->pipe([$prevCoord, $this->rounded($resPos)], self::MARKER.' Pipa Trunk Akhir', null, 400, 'DI');
        $edges[] = [$pipe, $prevNode, $reservoir];

        // ── 3. edge graf (unik per pasangan node; chunked utk ribuan baris) ──
        $uniq = [];
        foreach ($edges as [$pipe, $from, $to]) {
            $uniq[$pipe.':'.min($from, $to).'-'.max($from, $to)] = [$pipe, $from, $to];
        }
        foreach (array_chunk(array_values($uniq), 500) as $chunk) {
            DB::table('gis_network_edges')->insert(
                array_map(fn ($e) => $this->edgeRow($e[0], $e[1], $e[2]), $chunk)
            );
        }

        // ── 4. DMA per zona dari hull rumah ter-buffer + reading suplai bulanan
        foreach ($clusters as $c) {
            $c['dma_id'] = $this->upsertDma($c);
            $this->seedPreviousMonthReadings($c);
        }

        TenantContext::clear();
        $this->command?->info('[NET] '.self::MARKER.': '.count($clusters).' rute → trunk+distro+DMA dari koordinat rumah nyata.');
    }

    // ── data gathering ─────────────────────────────────────────────────────

    /** @return array<int,array> klaster per zona, urut bujur (barat→timur). */
    private function clustersFromCustomers(): array
    {
        $clusters = [];
        foreach (DB::table('zones')->where('pdam_org_id', $this->orgId)->get(['id', 'code', 'name']) as $zone) {
            $pts = DB::table('customers')
                ->where('pdam_org_id', $this->orgId)->where('zone_id', $zone->id)
                ->where('status', '!=', 'inactive')
                ->whereNotNull('latitude')->whereNotNull('longitude')
                ->orderBy('id')
                ->get(['id', 'customer_number', 'longitude', 'latitude'])
                ->map(fn ($r) => [
                    'id' => (int) $r->id, 'number' => $r->customer_number,
                    'lng' => (float) $r->longitude, 'lat' => (float) $r->latitude,
                ])->all();
            if (count($pts) < 2) {
                continue;
            }
            $n = count($pts);
            $centroid = [array_sum(array_column($pts, 'lng')) / $n, array_sum(array_column($pts, 'lat')) / $n];
            $pairs = array_map(fn ($p) => [$p['lng'], $p['lat']], $pts);
            $ext = ['N' => $pairs[0], 'S' => $pairs[0], 'E' => $pairs[0], 'W' => $pairs[0]];
            foreach ($pairs as $p) {
                if ($p[1] > $ext['N'][1]) {
                    $ext['N'] = $p;
                }
                if ($p[1] < $ext['S'][1]) {
                    $ext['S'] = $p;
                }
                if ($p[0] > $ext['E'][0]) {
                    $ext['E'] = $p;
                }
                if ($p[0] < $ext['W'][0]) {
                    $ext['W'] = $p;
                }
            }
            $hull = $this->geo->regionPolygon($pairs) ?: array_values($ext);
            $clusters[] = [
                'zone_id' => $zone->id, 'zone_code' => $zone->code, 'zone_name' => $zone->name,
                'count' => $n, 'centroid' => $centroid, 'extremes' => $ext, 'houses' => $pts,
                'hull' => $this->bufferHull($hull, $centroid),
            ];
        }
        usort($clusters, fn ($a, $b) => $a['centroid'][0] <=> $b['centroid'][0]);

        return $clusters;
    }

    /**
     * Jaringan distribusi SATU zona: node tap utk SETIAP rumah (koordinat persis
     * pelanggan), lalu Prim MST dari valve cabang zona → semua rumah tersambung
     * pipa (SR Ø20 PE; ruas dgn ≥2/≥3 keturunan jadi Ø75/Ø110). Percabangan
     * ≥3 anak di-upgrade jadi VALVE (granularitas isolasi spt jaringan nyata);
     * dua tap terluar N/S jadi hydrant.
     *
     * @return array<int, array{0:int,1:int,2:int}> trips [pipe_id, from_node, to_node]
     */
    private function buildZoneNetwork(array $c, int $valveId): array
    {
        $houses = $c['houses'];
        $n = count($houses);
        if ($n === 0) {
            return [];
        }

        $out = [];
        $tap = [];   // [i => ['id','coord','number']]
        $seenCoords = [];
        foreach ($houses as $idx => $h) {
            $coord = $this->rounded([$h['lng'], $h['lat']]);
            // rumah kembar koordinat identik → geser mikro ~0,5 m agar tetap
            // satu tap per rumah (graf tak punya simpul yatim).
            while (isset($seenCoords[$coord[0].','.$coord[1]])) {
                $coord = [round($coord[0] + 0.0000050, 7), round($coord[1] + 0.0000050, 7)];
            }
            $seenCoords[$coord[0].','.$coord[1]] = true;
            $tap[$idx] = [
                'id' => $this->node('tap', $coord, 'SMBS-TAP '.$h['number'], $c['zone_id']),
                'coord' => $coord, 'number' => $h['number'],
            ];
        }

        // ── Prim MST, root virtual (index -1) = valve cabang zona ──────────
        $root = $this->rounded($c['centroid']);
        $inT = array_fill(0, $n, false);
        $parent = array_fill(0, $n, -1);          // -1 ⇒ sambung langsung ke valve
        $minD = array_fill(0, $n, INF);
        $deg = array_fill(0, $n, 0);              // jumlah anak tiap tap
        $degRoot = 0;
        for ($j = 0; $j < $n; $j++) {
            $minD[$j] = $this->geo->distanceMeters($root[1], $root[0], $tap[$j]['coord'][1], $tap[$j]['coord'][0]);
        }

        for ($step = 0; $step < $n; $step++) {
            $best = -1;
            $bestD = INF;
            for ($j = 0; $j < $n; $j++) {
                if (! $inT[$j] && $minD[$j] < $bestD) {
                    $bestD = $minD[$j];
                    $best = $j;
                }
            }
            $inT[$best] = true;
            if ($parent[$best] === -1) {
                $degRoot++;
            } else {
                $deg[$parent[$best]]++;
            }

            $bc = $tap[$best]['coord'];
            for ($j = 0; $j < $n; $j++) {
                if ($inT[$j]) {
                    continue;
                }
                $d = $this->geo->distanceMeters($bc[1], $bc[0], $tap[$j]['coord'][1], $tap[$j]['coord'][0]);
                if ($d < $minD[$j]) {
                    $minD[$j] = $d;
                    $parent[$j] = $best;
                }
            }
        }

        // ── upgrade peran node: hub(≥4 anak)→valve, dua tap terluar→hydrant ─
        foreach ($tap as $idx => $t) {
            if ($deg[$idx] >= 4) {
                $this->retypeNode($t['id'], 'valve', 'SMBS-VLV '.$t['number'].' (hub '.$deg[$idx].')');
            }
        }
        foreach (['N' => $c['extremes']['N'], 'S' => $c['extremes']['S']] as $dir => $ex) {
            $bestIdx = null;
            $bestD = INF;
            foreach ($tap as $idx => $t) {
                if (($this->typeByNodeId[$t['id']] ?? 'tap') !== 'tap') {
                    continue; // jangan timpa hub valve
                }
                $d = $this->geo->distanceMeters($ex[1], $ex[0], $t['coord'][1], $t['coord'][0]);
                if ($d < $bestD) {
                    $bestD = $d;
                    $bestIdx = $idx;
                }
            }
            if ($bestIdx !== null) {
                $this->retypeNode($tap[$bestIdx]['id'], 'hydrant', 'SMBS-HYD '.$c['zone_code'].'-'.$dir.' '.$tap[$bestIdx]['number']);
            }
        }

        // ── pipa sambungan + edge utk semua tap ────────────────────────────
        foreach ($tap as $idx => $t) {
            $pIdx = $parent[$idx];
            $pCoord = $pIdx === -1 ? $root : $tap[$pIdx]['coord'];
            $pNode = $pIdx === -1 ? $valveId : $tap[$pIdx]['id'];
            [$dia, $mat] = $deg[$idx] >= 3 ? [110, 'PVC'] : ($deg[$idx] === 2 ? [75, 'PVC'] : [20, 'PE']);
            $pipe = $this->pipeSrv([$pCoord, $t['coord']], 'SMBS-SVC '.$t['number'], $c['zone_id'], $dia, $mat, (int) $houses[$idx]['id']);
            $out[] = [$pipe, $pNode, $t['id']];
        }

        return $out;
    }

    /** Ganti feature_type node tanpa membuat ulang (id & edge tetap valid). */
    private function retypeNode(int $id, string $type, string $name): void
    {
        DB::table('gis_features')->where('id', $id)->update([
            'feature_type' => $type, 'name' => $name, 'updated_at' => $this->now,
        ]);
        $this->typeByNodeId[$id] = $type;
    }

    // ── builders (id node dipakai ulang di edge → graf tersambung tepat) ───

    private function node(string $type, array $lngLat, string $name, ?int $zoneId): int
    {
        $id = DB::table('gis_features')->insertGetId([
            'pdam_org_id' => $this->orgId, 'feature_type' => $type, 'name' => $name,
            'geometry' => json_encode(['type' => 'Point', 'coordinates' => $this->rounded($lngLat)]),
            'properties' => json_encode(['seed' => 'pipenet']), 'zone_id' => $zoneId, 'status' => 'active',
            'created_at' => $this->now, 'updated_at' => $this->now,
        ]);
        $this->typeByNodeId[$id] = $type;

        return $id;
    }

    private function pipe(array $coords, string $name, ?int $zoneId, int $dia, string $material): int
    {
        return $this->pipeSrv($coords, $name, $zoneId, $dia, $material, null);
    }

    /** Pipeline dgn reference pelanggan (SR) — property service_for → UI bisa
     *  tampilkan "sambungan utk SMBS-xxxxx". */
    private function pipeSrv(array $coords, string $name, ?int $zoneId, int $dia, string $material, ?int $forCustomer): int
    {
        $coords = array_map(fn ($p) => $this->rounded($p), $coords);
        $props = [
            'diameter_mm' => $dia, 'material' => $material, 'install_year' => 2025,
            'length_meters' => round($this->geo->lineLengthMeters($coords), 1), 'seed' => 'pipenet',
        ];
        if ($forCustomer !== null) {
            $props['service_for'] = $forCustomer;
        }

        return DB::table('gis_features')->insertGetId([
            'pdam_org_id' => $this->orgId, 'feature_type' => 'pipe', 'name' => $name,
            'geometry' => json_encode(['type' => 'LineString', 'coordinates' => $coords]),
            'properties' => json_encode($props),
            'zone_id' => $zoneId, 'status' => 'active', 'created_at' => $this->now, 'updated_at' => $this->now,
        ]);
    }

    private function edgeRow(int $pipeId, int $from, int $to): array
    {
        return [
            'pdam_org_id' => $this->orgId, 'pipe_feature_id' => $pipeId,
            'from_node_id' => $from, 'from_node_type' => $this->typeByNodeId[$from] ?? 'junction',
            'to_node_id' => $to, 'to_node_type' => $this->typeByNodeId[$to] ?? 'junction',
            'length_meters' => null, 'created_at' => $this->now, 'updated_at' => $this->now,
        ];
    }

    /** Buffer hull keluar ≥250 m agar semua rumah tepi masuk polygon DMA. */
    private function bufferHull(array $hull, array $centroid): array
    {
        [$cx, $cy] = $centroid;
        $out = [];
        foreach ($hull as [$x, $y]) {
            $dx = $x - $cx;
            $dy = $y - $cy;
            $d = sqrt($dx * $dx + $dy * $dy) ?: 1e-9;
            $push = max($d * 0.35, 0.0028);
            $out[] = [round($cx + $dx / $d * ($d + $push), 7), round($cy + $dy / $d * ($d + $push), 7)];
        }
        if (count($out) >= 3) {
            $out[] = $out[0]; // tutup ring
        }

        return $out;
    }

    private function upsertDma(array $c): int
    {
        $boundary = json_encode(['type' => 'Polygon', 'coordinates' => [$c['hull']]]);
        $existing = DB::table('dma_zones')->where('pdam_org_id', $this->orgId)->where('zone_id', $c['zone_id'])->orderBy('id')->first();
        if ($existing) {
            DB::table('dma_zones')->where('id', $existing->id)->update([
                'boundary' => $boundary, 'name' => 'DMA '.$c['zone_name'],
                'total_connections' => $c['count'], 'is_active' => true, 'updated_at' => $this->now,
            ]);

            return (int) $existing->id;
        }
        $id = (int) DB::table('dma_zones')->insertGetId([
            'pdam_org_id' => $this->orgId, 'zone_id' => $c['zone_id'],
            'code' => 'DMA-'.substr($c['zone_code'], -2), 'name' => 'DMA '.$c['zone_name'],
            'boundary' => $boundary, 'total_connections' => $c['count'],
            'base_demand_m3day' => max(10, $c['count'] * 10), 'is_active' => true,
            'created_at' => $this->now, 'updated_at' => $this->now,
        ]);

        return $id;
    }

    /**
     * Reading suplai jam-jaman untuk PERIODO TERAKHIR YANG TUTUP (bulan lalu) —
     * flow = konsumsi nyata wilayah / jam × 1.35 → NRW demo wajar (~26%),
     * supaya panel NRW dashboard berisi data integrated dgn tagihan & rumah.
     */
    private function seedPreviousMonthReadings(array $c): void
    {
        if (empty($c['dma_id'])) {
            return;
        }
        $period = now()->startOfMonth()->subMonthNoOverflow()->format('Y-m');
        $days = (int) Carbon::parse($period.'-01')->daysInMonth;
        $total = (float) DB::table('bills')
            ->join('customers', 'customers.id', '=', 'bills.customer_id')
            ->where('bills.pdam_org_id', $this->orgId)
            ->where('bills.period', $period)
            ->where('customers.zone_id', $c['zone_id'])
            ->sum('bills.consumption');
        $total = max($total, $days * 24); // guard flow ≥1 m³h
        $baseFlow = $total / ($days * 24) * 1.35;

        $rows = [];
        for ($d = 1; $d <= $days; $d++) {
            for ($h = 0; $h < 24; $h++) {
                $wave = 1 + 0.25 * sin(($h - 7) / 24 * 2 * M_PI);       // pola harian pagi/sore
                $jitter = 1 + ((($d * 24 + $h + $c['zone_id'] * 7) % 11) - 5) / 100; // ±5% deterministik
                $rows[] = [
                    'pdam_org_id' => $this->orgId, 'dma_zone_id' => $c['dma_id'],
                    'reading_at' => sprintf('%s-%02d-%02d %02d:00:00', substr($period, 0, 4), (int) substr($period, 5, 2), $d, $h),
                    'flow_rate_m3h' => round($baseFlow * $wave * $jitter, 2),
                    'pressure_bar' => round(2.2 + (($h % 5) * 0.15), 2),
                    'reservoir_level_percent' => round(60 + 20 * sin($h / 24 * M_PI), 1),
                    'chlorine_residual' => round(0.4 + (($d % 4) * 0.05), 2),
                    'created_at' => $this->now, 'updated_at' => $this->now,
                ];
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('distribution_readings')->insert($chunk);
        }
    }

    private function rounded(array $lngLat): array
    {
        return [round((float) $lngLat[0], 7), round((float) $lngLat[1], 7)];
    }
}
