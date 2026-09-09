<?php

namespace App\Services\Geo;

use App\Models\Customer;
use App\Models\DmaZone;
use App\Models\GisFeature;
use App\Models\GisNetworkEdge;
use Illuminate\Support\Collection;

/**
 * NetworkGraphService — graf jaringan perpipaan BERARAH untuk dashboard GIS.
 *
 * Konsep:
 *  - Node = GisFeature Point  (valve | junction | hydrant | pump | reservoir | intake)
 *  - Edge = GisNetworkEdge    (from_node ↔ to_node, opsional pipe_feature_id → ruas pipa LineString)
 *  - ARAH ALIRAN: edge diorientasikan upstream→downstream lewat BFS mulai dari
 *    node sumber (pump/reservoir/intake/treatment). Grafik loop tak berarah di-
 *    orientasikan mengikuti pohon BFS (edge silang diarahkan dari hulu→hilir).
 *  - Sumber air = batas hulu alami (tidak dilewati flood).
 *  - Valve terbuka  = barrier yang DIISOLASI (harus ditutup).
 *  - Valve tertutup = barrier yang sudah memutus aliran (tidak dihitung).
 *
 * isolate(): (1) temukan valve frontier di kedua sisi ruas bocor lewat walk
 * terarah, (2) SIMULASI tutup valve-valve itu pada graf terarah, (3) wilayah
 * terdampak = node/pipa yang sebelumnya teraliri kini kehilangan air —
 * rumah yang masih teraliri via jalur lain TIDAK ikut terpotong.
 *
 * affectedCustomersInRegion(): ambil hull dari geometri segmen terdampak, lalu
 * point-in-polygon ke koordinat pelanggan.
 */
class NetworkGraphService
{
    /** Tipe node yang dianggap sumber air. */
    public const SOURCE_TYPES = ['pump', 'reservoir', 'intake', 'treatment'];

    public const BARRIER_TYPES = ['valve'];

    /**
     * Isolasian ruas pipa (atau node) yang rusak/bocor dengan SIMULASI penutupan
     * valve pada graf terarah (arah aliran = BFS dari sumber).
     *
     * 1. Walk frontier dua arah dari ruas bocor: valve boundary yang harus ditutup
     *    + sumber yang (bahaya) masih menempel tanpa valve.
     * 2. Simulasi: jaringan air sebelum vs sesudah valve frontier ditutup.
     * 3. Terdampak = yang sebelumnya teraliri dan sesudah penutupan KEHILANGAN
     *    air — cabang yang masih teraliri via jalur lain TIDAK ikut terpotong.
     *
     * @return array{ok:bool, valves_to_close:array<int,array>, valves_closed:array<int,array>,
     *               isolated_pipes:array<int,array>, isolated_nodes:array<int,array>,
     *               reached_sources:array<int,array>, note:string}
     */
    public function isolate(int $featureId): array
    {
        $burst = GisFeature::find($featureId);
        if (! $burst) {
            return $this->emptyResult('Fitur tidak ditemukan.');
        }

        $nodes = GisFeature::whereIn('feature_type', ['valve', 'junction', 'hydrant', 'pump', 'reservoir', 'intake', 'treatment'])
            ->get()->keyBy('id');
        $pipes = GisFeature::where('feature_type', 'pipe')->get()->keyBy('id');
        $edges = $this->loadEdges();
        $orientation = $this->orient($edges, $nodes);

        $seeds = [];
        $leakEdgeIds = [];
        if ($burst->feature_type === 'pipe') {
            foreach ($edges as $e) {
                if ($e->pipe === $featureId) {
                    $seeds = array_values(array_filter([$e->from, $e->to]));
                    $leakEdgeIds[$e->id] = true;
                }
            }
        }
        if ($seeds === [] && $nodes->has($featureId)) {
            $seeds = [$featureId];
            foreach ($edges as $e) {
                if ($e->from === $featureId || $e->to === $featureId) {
                    $leakEdgeIds[$e->id] = true;
                }
            }
        }
        if ($seeds === []) {
            return $this->emptyResult('Pipa belum terhubung ke jaringan (buat edge/sambungkan ke node lewat editor).');
        }

        // (1) valve frontier + sumber yang menempel (di luar zona bocor).
        $valvesToClose = [];
        $alreadyClosed = [];
        $reachedSources = [];
        foreach ($seeds as $seed) {
            $this->collectFrontiers($seed, $edges, $nodes, $leakEdgeIds, $valvesToClose, $alreadyClosed, $reachedSources);
        }
        // Minimalisasi: valve ikut-ikutan (mis. cabang hilir buntu) TIDAK usah ditutup.
        $valvesToClose = array_fill_keys(
            $this->minimizeCut(array_keys($valvesToClose), $seeds, $edges, $orientation, $nodes, array_keys($leakEdgeIds)),
            true
        );

        // (2)+(3) simulasi: operasi normal (fedBefore) vs sesudah valve frontier
        // ditutup DAN ruas bocor diangkat (fedAfter). starved = dulu teraliri,
        // sekarang tidak — cabang yang tetap teraliri via jalur lain dikecualikan.
        $fedBefore = $this->flowReachable($edges, $orientation, $nodes, [], []);
        $fedAfter = $this->flowReachable($edges, $orientation, $nodes, array_keys($leakEdgeIds), array_keys($valvesToClose));
        $starved = array_values(array_filter(array_keys($fedBefore), fn ($n) => ! isset($fedAfter[$n])));
        $starvedSet = array_flip($starved);

        $isolatedNodeIds = array_values(array_unique(array_merge($seeds, $starved)));

        $isolatedPipeIds = [$featureId => true];
        $seedSet = array_flip($seeds);
        $noWater = fn (int $id): bool => ! isset($fedAfter[$id]);
        foreach ($edges as $e) {
            if (! $e->pipe || isset($leakEdgeIds[$e->id])) {
                continue;
            }
            // segmen mati bila kedua ujungnya tanpa air dan minimal satu ujung
            // memang TERDAMPAK penutupan (bukan klaster yatim dari awal).
            $touchedByStarvation = (isset($starvedSet[$e->from]) || isset($starvedSet[$e->to]))
                || (isset($seedSet[$e->from]) && isset($seedSet[$e->to]));
            if ($noWater($e->from) && $noWater($e->to) && $touchedByStarvation) {
                $isolatedPipeIds[$e->pipe] = true;
            }
        }
        $isolatedPipeIds = array_filter($isolatedPipeIds, fn ($p, $pid) => $pid !== 0 && $pipes->has($pid), ARRAY_FILTER_USE_BOTH);

        $note = null;
        $stillFed = array_values(array_filter($seeds, fn ($s) => isset($fedAfter[$s]) || in_array($nodes->get($s)?->feature_type ?? '', self::SOURCE_TYPES, true)));
        if ($valvesToClose === [] && $reachedSources !== []) {
            $note = 'Tidak ada valve pemutus ke sumber — matikan pompa/sumber sepihak utk ruas ini.';
        } elseif ($stillFed !== []) {
            $note = 'Valve zona sudah ditutup, tapi masih ada sisi ruas menempel ke sumber — matikan pompa utk kerja aman.';
        } elseif ($valvesToClose === [] && $starved === []) {
            $note = 'Zona sekitar bocor sudah terlanjur mati (tidak teraliri sumber aktif) — periksa valve hulu / integritas sambungan.';
        } elseif ($starved === []) {
            $note = 'Valve frontier ditemukan, tapi tidak ada segmen yang kehilangan air — cek arah aliran / jalur lain.';
        }
        if ($note === null) {
            $note = sprintf(
                '%d valve ditutup → %d segmen tambahan kehilangan suplai (yang masih teraliri via jalur lain dikecualikan).',
                count($valvesToClose),
                max(0, count($isolatedPipeIds) - 1)
            );
        }

        $featureInfo = fn ($id) => optional($nodes->get($id) ?? $pipes->get($id), fn ($f) => [
            'id' => $f->id, 'name' => $f->name, 'type' => $f->feature_type, 'status' => $f->status,
        ]);

        return [
            'ok' => $valvesToClose !== [] || $reachedSources === [],
            'valves_to_close' => array_values(array_filter(array_map($featureInfo, array_keys($valvesToClose)))),
            'valves_closed' => array_values(array_filter(array_map($featureInfo, array_keys($alreadyClosed)))),
            'isolated_pipes' => array_values(array_filter(array_map($featureInfo, array_keys($isolatedPipeIds)))),
            'isolated_nodes' => array_values(array_filter(array_map($featureInfo, $isolatedNodeIds))),
            'reached_sources' => array_values(array_filter(array_map($featureInfo, array_keys($reachedSources)))),
            'isolated_pipe_id_list' => array_values(array_keys($isolatedPipeIds)),
            'note' => $note,
        ];
    }

    /** @return Collection<int, object{id:int, pipe:int|null, from:int, to:int}> */
    private function loadEdges(): Collection
    {
        return GisNetworkEdge::get()->map(fn ($e) => (object) [
            'id' => $e->id, 'pipe' => $e->pipe_feature_id,
            'from' => $e->from_node_id, 'to' => $e->to_node_id,
        ]);
    }

    /** Arah aliran semua edge (keyed edge id) — utk layer panah peta & validator. */
    public function edgeOrientations(): array
    {
        $nodes = GisFeature::whereIn('feature_type', ['valve', 'junction', 'hydrant', 'pump', 'reservoir', 'intake', 'treatment'])
            ->get()->keyBy('id');

        return $this->orient($this->loadEdges(), $nodes);
    }

    /**
     * Orientasi arah aliran: BFS multi-sumber pada graf fisik; edge pohon BFS
     * diarah parent→child (hulu→hilir), edge silang dari depth lebih dangkal.
     * Node tak terjangkau sumber (klaster yatim) → pakai urutan aslinya.
     *
     * @return array<int, array{from:int, to:int}> keyed edge id
     */
    public function orient(Collection $edges, Collection $nodes): array
    {
        $adj = [];
        foreach ($edges as $e) {
            if (! $e->from || ! $e->to) {
                continue;
            }
            $adj[$e->from][] = ['to' => $e->to, 'edge' => $e->id];
            $adj[$e->to][] = ['to' => $e->from, 'edge' => $e->id];
        }

        $depth = [];
        $queue = [];
        foreach ($nodes as $id => $node) {
            if (in_array($node->feature_type, self::SOURCE_TYPES, true)) {
                $depth[$id] = 0;
                $queue[] = $id;
            }
        }

        $orient = [];
        while ($queue) {
            $nid = array_shift($queue);
            foreach ($adj[$nid] ?? [] as $link) {
                if (isset($orient[$link['edge']])) {
                    continue;
                }
                if (! isset($depth[$link['to']])) {
                    $depth[$link['to']] = ($depth[$nid] ?? 0) + 1;
                    $orient[$link['edge']] = ['from' => $nid, 'to' => $link['to']];
                    $queue[] = $link['to'];
                }
            }
        }

        // edge silang (cycle) → dangkal→dalam; edge di komponen tanpa sumber → urutan asli.
        foreach ($edges as $e) {
            if (isset($orient[$e->id])) {
                continue;
            }
            $df = $depth[$e->from] ?? null;
            $dt = $depth[$e->to] ?? null;
            $orient[$e->id] = match (true) {
                $df !== null && $dt !== null && $df > $dt => ['from' => $e->to, 'to' => $e->from],
                default => ['from' => $e->from, 'to' => $e->to],
            };
        }

        return $orient;
    }

    /**
     * Jalan kaki frontier dari satu seed: temui valve (butuh tutup / sudah
     * tertutup) atau sumber → berhenti di situ; selain itu terus menyusuri
     * jaringan fisik (dua arah, valve tidak dilewati).
     *
     * @param  array<int,bool>  $leakEdgeIds
     */
    private function collectFrontiers(int $seed, Collection $edges, Collection $nodes, array $leakEdgeIds, array &$valvesToClose, array &$valvesClosed, array &$sources): void
    {
        $check = function (int $id) use ($nodes, &$valvesToClose, &$valvesClosed, &$sources): ?string {
            $node = $nodes->get($id);
            $type = $node?->feature_type ?? '';
            if (in_array($type, self::BARRIER_TYPES, true)) {
                if (($node->status ?? 'active') === 'closed') {
                    $valvesClosed[$id] = true;
                } else {
                    $valvesToClose[$id] = true;
                }

                return 'valve';
            }
            if (in_array($type, self::SOURCE_TYPES, true)) {
                $sources[$id] = true;

                return 'source';
            }

            return null;
        };

        if ($check($seed) !== null) {
            return;
        }

        $visited = [$seed => true];
        $queue = [$seed];
        while ($queue) {
            $nid = array_shift($queue);
            foreach ($edges as $e) {
                if (isset($leakEdgeIds[$e->id])) {
                    continue;
                }
                $other = $e->from === $nid ? $e->to : ($e->to === $nid ? $e->from : null);
                if ($other === null || isset($visited[$other])) {
                    continue;
                }
                $visited[$other] = true;
                if ($check($other) === null) {
                    $queue[] = $other;
                }
            }
        }
    }

    /**
     * Node yang TERALIRI air: BFS dari seluruh sumber mengikuti arah aliran;
     * valve yang ditutup ($closeValveIds) atau berstatus closed jadi batas;
     * edge di $removeEdgeIds (ruas bocor/servis) tidak dialiri.
     *
     * @param  array<int, array{from:int,to:int}>  $orientation
     * @param  array<int>  $removeEdgeIds  list edge id yang diangkat (ruas bocor)
     * @return array<int, true> node id => true
     */
    private function flowReachable(Collection $edges, array $orientation, Collection $nodes, array $removeEdgeIds, array $closeValveIds): array
    {
        $down = [];
        foreach ($edges as $e) {
            if (in_array($e->id, $removeEdgeIds, true) || ! isset($orientation[$e->id])) {
                continue;
            }
            $o = $orientation[$e->id];
            $down[$o['from']][] = $o['to'];
        }

        $isBarrier = function (int $id) use ($nodes, $closeValveIds): bool {
            if (in_array($id, $closeValveIds, true)) {
                return true;
            }
            $node = $nodes->get($id);

            return $node && $node->feature_type === 'valve' && ($node->status ?? 'active') === 'closed';
        };

        $fed = [];
        $queue = [];
        foreach ($nodes as $id => $node) {
            if (in_array($node->feature_type, self::SOURCE_TYPES, true) && ! isset($fed[$id])) {
                $fed[$id] = true;
                $queue[] = $id;
            }
        }

        while ($queue) {
            $nid = array_shift($queue);
            foreach ($down[$nid] ?? [] as $next) {
                if ($isBarrier($next) || isset($fed[$next])) {
                    continue;
                }
                $fed[$next] = true;
                $queue[] = $next;
            }
        }

        return $fed;
    }

    /**
     * Buang valve tak genting dari candidato rim: suatu valve tetap diperlukan
     * hanya jika TANPA dia masih ada seed ruas bocor yang kebagian air. Kalau
     * set penuh pun tidak mengisolasi (mis. sumber nempel langsung ke ruas),
     * kembalikan utuh — peringatan ditangani note/reached_sources.
     *
     * @param  array<int>  $cut
     * @param  array<int>  $seeds
     * @param  array<int, array{from:int,to:int}>  $orientation
     * @param  array<int>  $removeEdgeIds
     * @return array<int>
     */
    private function minimizeCut(array $cut, array $seeds, Collection $edges, array $orientation, Collection $nodes, array $removeEdgeIds): array
    {
        $isolates = function (array $candidate) use ($seeds, $edges, $orientation, $nodes, $removeEdgeIds): bool {
            $fed = $this->flowReachable($edges, $orientation, $nodes, $removeEdgeIds, $candidate);
            foreach ($seeds as $s) {
                if (isset($fed[$s])) {
                    return false;
                }
            }

            return true;
        };

        if ($cut === [] || ! $isolates($cut)) {
            return $cut;
        }

        foreach ($cut as $valveId) {
            $without = array_values(array_filter($cut, fn ($v) => $v !== $valveId));
            if ($isolates($without)) {
                $cut = $without;
            }
        }

        return $cut;
    }

    /**
     * Pelanggan terdampak = yang koodinatnya di dalam polygon pembatas
     * (convex hull) dari segmen terisolasi.
     *
     * @param  array<int>  $pipeIds
     * @return array{polygon:array, customers:array<int,array>, count:int}
     */
    public function affectedCustomersInRegion(array $pipeIds): array
    {
        $points = [];
        foreach (GisFeature::whereIn('id', $pipeIds)->where('feature_type', 'pipe')->get() as $pipe) {
            foreach ($pipe->geometry['coordinates'] ?? [] as $c) {
                $points[] = [(float) $c[0], (float) $c[1]]; // [lon, lat]
            }
        }

        $polygon = $this->regionPolygon($points);
        $query = Customer::query()
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->where('status', '!=', 'inactive');

        $customers = $polygon ? $query->cursor()->filter(
            fn ($c) => $this->pointInPolygon([(float) $c->longitude, (float) $c->latitude], $polygon) && $c->id
        )->map(fn ($c) => [
            'id' => $c->id,
            'customer_number' => $c->customer_number,
            'name' => $c->full_name,
            'phone' => $c->phone,
            'lat' => (float) $c->latitude,
            'lng' => (float) $c->longitude,
        ])->take(300)->values()->all() : [];

        return [
            'polygon' => $polygon,
            'customers' => $customers,
            'count' => $polygon ? $query->cursor()->filter(
                fn ($c) => $this->pointInPolygon([(float) $c->longitude, (float) $c->latitude], $polygon)
            )->count() : 0,
        ];
    }

    /**
     * Polygon区域: convex hull + buffer bbox bila kolinear (hull <3 titik).
     *
     * @param  array<int, array{0:float,1:float}>  $points  [lon,lat]
     */
    public function regionPolygon(array $points): ?array
    {
        if (count($points) < 3) {
            if ($points === []) {
                return null;
            }
            $lons = array_column($points, 0);
            $lats = array_column($points, 1);
            $minLon = min($lons);
            $maxLon = max($lons);
            $minLat = min($lats);
            $maxLat = max($lats);
            $padLon = max(($maxLon - $minLon) * 0.05, 0.001);
            $padLat = max(($maxLat - $minLat) * 0.05, 0.001);

            return [
                [$minLon - $padLon, $minLat - $padLat],
                [$maxLon + $padLon, $minLat - $padLat],
                [$maxLon + $padLon, $maxLat + $padLat],
                [$minLon - $padLon, $maxLat + $padLat],
            ];
        }

        return $this->convexHull($points);
    }

    /** Convex hull monotone-chain. @return array<int, array{0:float,1:float}> */
    public function convexHull(array $points): array
    {
        $make = fn ($pt) => (object) ['x' => (float) $pt[0], 'y' => (float) $pt[1]];
        $points = collect(array_map($make, $points))->sortBy('x')->sortBy('y')->values()->all();
        $n = count($points);
        if ($n < 3) {
            return array_map(fn ($p) => [$p->x, $p->y], $points);
        }

        $cross = fn ($o, $a, $b) => ($a->x - $o->x) * ($b->y - $o->y) - ($a->y - $o->y) * ($b->x - $o->x);

        $lower = [];
        foreach ($points as $p) {
            while (count($lower) >= 2 && $cross($lower[count($lower) - 2], $lower[count($lower) - 1], $p) <= 0) {
                array_pop($lower);
            }
            $lower[] = $p;
        }
        $upper = [];
        foreach (array_reverse($points) as $p) {
            while (count($upper) >= 2 && $cross($upper[count($upper) - 2], $upper[count($upper) - 1], $p) <= 0) {
                array_pop($upper);
            }
            $upper[] = $p;
        }
        array_pop($lower);
        array_pop($upper);

        return array_map(fn ($p) => [$p->x, $p->y], array_merge($lower, $upper));
    }

    /** Ray casting. $polygon [[lon,lat],...]; $point [lon,lat]. */
    public function pointInPolygon(array $point, array $polygon): bool
    {
        [$x, $y] = $point;
        $inside = false;
        $j = count($polygon) - 1;
        for ($i = 0; $i < count($polygon); $i++) {
            [$xi, $yi] = $polygon[$i];
            [$xj, $yj] = $polygon[$j];
            if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) {
                $inside = ! $inside;
            }
            $j = $i;
        }

        return $inside;
    }

    /** Jarak haversine meter antara dua [lat,lng]. */
    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Panjang garis LineString [lng,lat][] dalam meter. */
    public function lineLengthMeters(array $coordinates): float
    {
        $len = 0.0;
        for ($i = 1; $i < count($coordinates); $i++) {
            $len += $this->distanceMeters(
                (float) $coordinates[$i - 1][1], (float) $coordinates[$i - 1][0],
                (float) $coordinates[$i][1], (float) $coordinates[$i][0]
            );
        }

        return $len;
    }

    /**
     * Jarak terdekat titik (lat,lng) ke ruas pipa mana pun + titik sambungnya —
     * utk feasibility SR & validasi. Planar equirectangular lokal (cukup <2 km).
     *
     * @return array{pipe_id:int, distance_m:float, point:array{0:float,1:float}}|null
     */
    public function nearestPipe(float $lat, float $lng, float $maxM = 5000): ?array
    {
        $best = null;
        $bestD = INF;
        foreach (GisFeature::where('feature_type', 'pipe')->cursor() as $pipe) {
            $coords = $pipe->geometry['coordinates'] ?? [];
            if (count($coords) < 2) {
                continue;
            }
            // proyeksi lokal terhadap titik target
            $cosLat = cos(deg2rad($lat));
            $mx = fn ($lon) => ($lon - $lng) * 111320 * $cosLat;
            $my = fn ($lla) => ($lla - $lat) * 110540;
            $px = $mx($lng);
            $py = $my($lat);
            for ($i = 1; $i < count($coords); $i++) {
                $ax = $mx((float) $coords[$i - 1][0]);
                $ay = $my((float) $coords[$i - 1][1]);
                $bx = $mx((float) $coords[$i][0]);
                $by = $my((float) $coords[$i][1]);
                $d = $this->pointToSegmentM($px, $py, $ax, $ay, $bx, $by);
                if ($d < $bestD) {
                    $bestD = $d;
                    $best = [
                        'pipe_id' => (int) $pipe->id,
                        'distance_m' => round($d, 1),
                        'point' => [(float) $coords[$i][1], (float) $coords[$i][0]], // [lat,lng] ujung segmen
                    ];
                }
            }
        }
        if ($best && $best['distance_m'] <= $maxM) {
            return $best;
        }

        return null;
    }

    /** Jarak titik ke segmen dalam meter (planar lokal). */
    private function pointToSegmentM(float $px, float $py, float $ax, float $ay, float $bx, float $by): float
    {
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $len2 = $dx * $dx + $dy * $dy;
        if ($len2 <= 0) {
            return sqrt(($px - $ax) ** 2 + ($py - $ay) ** 2);
        }
        $t = max(0, min(1, (($px - $ax) * $dx + ($py - $ay) * $dy) / $len2));
        $qx = $ax + $t * $dx;
        $qy = $ay + $t * $dy;

        return sqrt(($px - $qx) ** 2 + ($py - $qy) ** 2);
    }

    /**
     * Auto-wire pipa hasil gambar: cari node endpoint terdekat (< $maxM),
     * kalau tidak ada → buat junction baru. Kembalikan [from, to] node + edge.
     *
     * @return array{from_node_id:int, to_node_id:int, created_junctions:array<int>}
     */
    public function ensureEndpoints(GisFeature $pipe, Collection $nodes, float $maxM = 12.0): array
    {
        $coords = $pipe->geometry['coordinates'] ?? [];
        if (count($coords) < 2) {
            throw new \InvalidArgumentException('Pipa LineString minimal 2 titik.');
        }
        $start = [(float) $coords[0][0], (float) $coords[0][1]];
        $end = [(float) $coords[count($coords) - 1][0], (float) $coords[count($coords) - 1][1]];
        $created = [];

        $resolve = function (array $lonLat) use ($nodes, $maxM, $pipe, &$created) {
            $best = null;
            $bestD = INF;
            foreach ($nodes as $node) {
                $nc = $node->geometry['coordinates'] ?? null;
                if (! $nc) {
                    continue;
                }
                $d = $this->distanceMeters((float) $lonLat[1], (float) $lonLat[0], (float) $nc[1], (float) $nc[0]);
                if ($d < $bestD) {
                    $bestD = $d;
                    $best = $node;
                }
            }
            if ($best && $bestD <= $maxM) {
                return $best->id;
            }

            $junction = GisFeature::create([
                'pdam_org_id' => $pipe->pdam_org_id,
                'feature_type' => 'junction',
                'name' => 'JCT-'.strtoupper(substr(uniqid(), -5)),
                'geometry' => ['type' => 'Point', 'coordinates' => $lonLat],
                'zone_id' => $pipe->zone_id,
                'status' => 'active',
            ]);
            $created[] = $junction->id;

            return $junction->id;
        };

        $fromId = $resolve($start);
        $toId = $resolve($end);

        $length = $this->lineLengthMeters($coords);
        $typeOf = fn ($id) => (string) ($nodes->get($id)?->feature_type ?? 'junction');
        GisNetworkEdge::create([
            'pdam_org_id' => $pipe->pdam_org_id,
            'pipe_feature_id' => $pipe->id,
            'from_node_id' => $fromId,
            'from_node_type' => $typeOf($fromId),
            'to_node_id' => $toId,
            'to_node_type' => $typeOf($toId),
            'length_meters' => round($length, 2),
        ]);

        return ['from_node_id' => $fromId, 'to_node_id' => $toId, 'created_junctions' => $created];
    }

    /** Hapus fitur + edge terkait (node: edge yang menempel; pipe: edge pipe-nya). */
    public function deleteFeatureWithEdges(GisFeature $feature): int
    {
        $q = GisNetworkEdge::query();
        if ($feature->feature_type === 'pipe') {
            $q->where('pipe_feature_id', $feature->id);
        } else {
            $q->where('from_node_id', $feature->id)->orWhere('to_node_id', $feature->id);
        }
        $deleted = $q->get()->count();
        $q->delete();
        $feature->delete();

        return $deleted;
    }

    /**
     * Audit kesehatan jaringan — bikin data demo & produksi selalu rapi.
     * Cek: node menggantung (tanpa edge), pipa belum tersambung edge,
     * klaster terisolasi (komponen tanpa sumber — kasus "yatim"),
     * ruas panjang tanpa valve di kedua ujung (tak bisa diisolasi),
     * DMA dengan hydrant di bawah minimum.
     *
     * @return array{score:int, checked_at:string, passed:int, counts:array<string,int>,
     *               issues:array<int,array{check:string,label:string,severity:string,count:int,deduction:int,items:array}>}
     */
    public function healthAudit(): array
    {
        $maxSegM = (float) config('business.gis.audit_uncontrolled_segment_m', 400);
        $minHydrants = (int) config('business.gis.audit_min_hydrants_per_dma', 2);

        /** @var Collection<int,GisFeature> $nodes */
        $nodes = GisFeature::whereIn('feature_type', ['valve', 'junction', 'hydrant', 'pump', 'reservoir', 'intake', 'treatment'])
            ->get()->keyBy('id');
        $pipes = GisFeature::where('feature_type', 'pipe')->get()->keyBy('id');
        $edges = $this->loadEdges();

        $degree = [];
        $pipeWithEdge = [];
        $byPipeEnds = [];       // pipe id → [[nodeA,nodeB], ...]
        foreach ($edges as $e) {
            if ($e->from && $e->to) {
                $degree[$e->from] = ($degree[$e->from] ?? 0) + 1;
                $degree[$e->to] = ($degree[$e->to] ?? 0) + 1;
                if ($e->pipe) {
                    $pipeWithEdge[$e->pipe] = true;
                    $byPipeEnds[$e->pipe][] = [$e->from, $e->to];
                }
            }
        }

        $featureInfo = fn (GisFeature $f) => ['id' => $f->id, 'name' => $f->name ?? ('#'.$f->id), 'type' => $f->feature_type];
        $shortList = fn (array $a) => array_slice($a, 0, 25);

        // 1) node menggantung
        $dangling = array_values(array_map($featureInfo, array_filter(
            $nodes->all(), fn (GisFeature $n) => ! ($degree[$n->id] ?? 0)
        )));

        // 2) pipa tanpa edge
        $orphanPipes = array_values(array_map($featureInfo, array_filter(
            $pipes->all(), fn (GisFeature $p) => ! isset($pipeWithEdge[$p->id])
        )));

        // 3) klaster tanpa sumber (komponen fisik tak berarah berukuran ≥2)
        $adj = [];
        foreach ($edges as $e) {
            if (! $e->from || ! $e->to) {
                continue;
            }
            $adj[$e->from][] = $e->to;
            $adj[$e->to][] = $e->from;
        }
        $seen = [];
        $isolated = [];
        foreach (array_keys($nodes->all()) as $n) {
            if (isset($seen[$n]) || $n === 0) {
                continue;
            }
            $queue = [$n];
            $comp = [];
            $hasSource = false;
            while ($queue) {
                $id = (int) array_shift($queue);
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $comp[] = $id;
                if (in_array($nodes->get($id)?->feature_type, self::SOURCE_TYPES, true)) {
                    $hasSource = true;
                }
                foreach ($adj[$id] ?? [] as $to) {
                    if (! isset($seen[$to])) {
                        $queue[] = $to;
                    }
                }
            }
            if (! $hasSource && count($comp) >= 2) {
                $isolated[] = ['size' => count($comp), 'members' => $shortList($comp)];
            }
        }

        // 4) ruas panjang tanpa valve di kedua ujungnya
        $longUncontrolled = [];
        foreach ($pipes as $p) {
            $len = (float) ($p->properties['length_meters'] ?? 0);
            if ($len <= 0) {
                $len = $this->lineLengthMeters($p->geometry['coordinates'] ?? []);
            }
            if ($len <= $maxSegM || empty($byPipeEnds[$p->id])) {
                continue;
            }
            $guarded = false;
            foreach ($byPipeEnds[$p->id] as [$a, $b]) {
                if (in_array($nodes->get($a)?->feature_type ?? '', self::BARRIER_TYPES, true)
                    || in_array($nodes->get($b)?->feature_type ?? '', self::BARRIER_TYPES, true)) {
                    $guarded = true;
                    break;
                }
            }
            if (! $guarded) {
                $longUncontrolled[] = ['id' => (int) $p->id, 'name' => $p->name ?? ('#'.$p->id), 'length_m' => round($len, 1)];
            }
        }

        // 5) hydrant minimum per DMA ber-polygon
        $hydrants = $nodes->filter(fn (GisFeature $n) => $n->feature_type === 'hydrant');
        $dmaFewHydrants = [];
        foreach (DmaZone::where('is_active', true)->whereNotNull('boundary')->cursor() as $dma) {
            $ring = $dma->boundary['coordinates'][0] ?? null;
            if (! is_array($ring) || count($ring) < 4) {
                continue;
            }
            $poly = array_map(fn ($pt) => [(float) $pt[0], (float) $pt[1]], $ring);
            $n = $hydrants->filter(function (GisFeature $h) use ($poly) {
                $c = $h->geometry['coordinates'] ?? null;

                return $c && $this->pointInPolygon([(float) $c[0], (float) $c[1]], $poly);
            })->count();
            if ($n < $minHydrants) {
                $dmaFewHydrants[] = ['dma_id' => (int) $dma->id, 'code' => $dma->code, 'name' => $dma->name, 'count' => $n, 'min' => $minHydrants];
            }
        }

        $checks = [];
        $deduct = function (string $check, string $label, string $severity, int $weight, int $cap, array $items, array $extra = []) use (&$checks): int {
            $count = count($items);
            $d = $count ? min($cap, $weight * $count) : 0;
            $checks[] = array_merge([
                'check' => $check, 'label' => $label, 'severity' => $severity,
                'count' => $count, 'deduction' => $d, 'items' => $items,
            ], $extra);

            return $d;
        };

        $totalDeduct = 0;
        $totalDeduct += $deduct('dangling_nodes', 'Node menggantung (belum ada sambungan)', 'warn', 2, 10, $shortList($dangling));
        $totalDeduct += $deduct('pipes_without_edges', 'Pipa belum terhubung ke graf (tanpa edge)', 'warn', 3, 12, $shortList($orphanPipes));
        $totalDeduct += $deduct('isolated_clusters', 'Klaster komponen tanpa sumber air (yatim)', 'crit', 6, 18, $shortList(array_map(
            fn ($c) => ['size' => $c['size'], 'node_ids' => $c['members']], $isolated
        )));
        $totalDeduct += $deduct('long_uncontrolled_segments', "Ruas > {$maxSegM} m tanpa valve pemutus di ujung", 'warn', 2, 10, array_values($shortList($longUncontrolled)));
        $totalDeduct += $deduct('hydrants_below_min', "Hydrant < {$minHydrants} per DMA", 'info', 2, 6, $shortList($dmaFewHydrants));

        return [
            'score' => max(0, 100 - $totalDeduct),
            'checked_at' => now()->toIso8601String(),
            'passed' => $totalDeduct === 0 ? 1 : 0,
            'counts' => [
                'nodes' => count($nodes), 'pipes' => count($pipes), 'edges' => count($edges),
                'dangling_nodes' => count($dangling), 'pipes_without_edges' => count($orphanPipes),
                'isolated_clusters' => count($isolated), 'long_uncontrolled_segments' => count($longUncontrolled),
                'hydrants_below_min' => count($dmaFewHydrants),
            ],
            'issues' => $checks,
        ];
    }

    private function emptyResult(string $note): array
    {
        return [
            'ok' => false, 'valves_to_close' => [], 'valves_closed' => [],
            'isolated_pipes' => [], 'isolated_nodes' => [], 'reached_sources' => [],
            'isolated_pipe_id_list' => [], 'note' => $note,
        ];
    }
}
