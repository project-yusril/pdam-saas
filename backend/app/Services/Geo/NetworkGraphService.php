<?php

namespace App\Services\Geo;

use App\Models\Customer;
use App\Models\GisFeature;
use App\Models\GisNetworkEdge;
use Illuminate\Support\Collection;

/**
 * NetworkGraphService — graf jaringan perpipaan untuk dashboard GIS.
 *
 * Konsep:
 *  - Node = GisFeature Point  (valve | junction | hydrant | pump | reservoir | intake)
 *  - Edge = GisNetworkEdge    (from_node ↔ to_node, opsional pipe_feature_id → ruas pipa LineString)
 *  - Sumber air = node dengan tipe pump/reservoir/intake (tidak dilewati flood, jadi batas).
 *  - Valve terbuka  = barrier yang DIISOLASI (harus ditutup).
 *  - Valve tertutup = barrier yang sudah memutus aliran (tidak dihitung).
 *
 * isolate(): cari valve minimal di SEKITAR titik kebocoran yang harus ditutup
 * agar ruas bocor terpotong dari semua sumber, lalu kumpulkan semua segmen
 * jaringan ikut terisolasi (kehilangan suplai).
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
     * Isolasian ruas pipa (atau node) yang rusak/bocor.
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

        $edges = GisNetworkEdge::get()->map(fn ($e) => (object) [
            'id' => $e->id, 'pipe' => $e->pipe_feature_id,
            'from' => $e->from_node_id, 'to' => $e->to_node_id,
        ]);

        $pipes = GisFeature::where('feature_type', 'pipe')->get()->keyBy('id');

        $seeds = [];
        if ($burst->feature_type === 'pipe') {
            foreach ($edges as $e) {
                if ($e->pipe === $featureId) {
                    $seeds = [$e->from, $e->to];
                    break;
                }
            }
        }
        if ($seeds === [] && $nodes->has($featureId)) {
            $seeds = [$featureId];
        }
        if ($seeds === []) {
            return $this->emptyResult('Pipa belum terhubung ke jaringan (buat edge/sambungkan ke node lewat editor).');
        }

        $adj = $this->buildAdjacency($edges);

        $valves = [];
        $alreadyClosed = [];
        $isolatedPipeIds = [$featureId => true];
        $isolatedNodeIds = [];
        $reachedSources = [];

        foreach ($seeds as $seed) {
            $walk = $this->floodFrom($seed, $adj, $nodes);
            foreach ($walk['valves_to_close'] as $v) {
                $valves[$v] = true;
            }
            foreach ($walk['valves_closed'] as $v) {
                $alreadyClosed[$v] = true;
            }
            foreach ($walk['pipes'] as $p) {
                $isolatedPipeIds[$p] = true;
            }
            foreach ($walk['nodes'] as $n) {
                $isolatedNodeIds[$n] = true;
            }
            foreach ($walk['sources'] as $s) {
                $reachedSources[$s] = true;
            }
        }

        $note = $reachedSources === []
            ? 'Ruas terisolasi penuh oleh valve.'
            : 'Masih terhubung ke sumber tanpa valve pemutus — periksa node sumber / tambah valve.';

        $featureInfo = fn ($id) => optional($nodes->get($id) ?? $pipes->get($id), fn ($f) => [
            'id' => $f->id, 'name' => $f->name, 'type' => $f->feature_type, 'status' => $f->status,
        ]);

        return [
            'ok' => $valves !== [] || $reachedSources === [],
            'valves_to_close' => array_values(array_filter(array_map($featureInfo, array_keys($valves)))),
            'valves_closed' => array_values(array_filter(array_map($featureInfo, array_keys($alreadyClosed)))),
            'isolated_pipes' => array_values(array_filter(array_map($featureInfo, array_keys($isolatedPipeIds)))),
            'isolated_nodes' => array_values(array_filter(array_map($featureInfo, array_keys($isolatedNodeIds)))),
            'reached_sources' => array_values(array_filter(array_map($featureInfo, array_keys($reachedSources)))),
            'isolated_pipe_id_list' => array_keys($isolatedPipeIds),
            'note' => $note,
        ];
    }

    /**
     * Flood plain DFS dari satu seed node: kumpulkan segmen/node terdampak,
     * valve pemutus, dan sumber yang (bahaya) masih nyambung.
     *
     * @return array{valves_to_close:array<int>, valves_closed:array<int>, pipes:array<int>, nodes:array<int>, sources:array<int>}
     */
    private function floodFrom(int $seed, array $adj, Collection $nodes): array
    {
        $out = ['valves_to_close' => [], 'valves_closed' => [], 'pipes' => [], 'nodes' => [$seed], 'sources' => []];
        $visited = [$seed => true];
        $queue = [$seed];

        while ($queue) {
            $nodeId = array_shift($queue);
            $node = $nodes->get($nodeId);
            $type = $node?->feature_type ?? 'junction';

            // masuk valve → ditutup (barrier); sudah closed juga jadi barrier.
            if ($node && in_array($type, self::BARRIER_TYPES, true)) {
                if (($node->status ?? 'active') === 'closed') {
                    $out['valves_closed'][] = $nodeId;
                } else {
                    $out['valves_to_close'][] = $nodeId;
                }

                continue;
            }
            // sumber air → stop, tandai peringatan bila tanpa valve.
            if ($node && in_array($type, self::SOURCE_TYPES, true)) {
                $out['sources'][] = $nodeId;

                continue;
            }

            foreach ($adj[$nodeId] ?? [] as $link) {
                $out['pipes'][] = $link['pipe'];
                $out['nodes'][] = $link['to'];
                if (isset($visited[$link['to']])) {
                    continue;
                }
                $visited[$link['to']] = true;
                $queue[] = $link['to'];
            }
        }

        foreach (['valves_to_close', 'valves_closed', 'pipes', 'nodes', 'sources'] as $k) {
            $out[$k] = array_values(array_unique(array_filter($out[$k])));
        }

        return $out;
    }

    /** @return array<int, array<int, array{to:int, pipe:int|null}>> */
    private function buildAdjacency(Collection $edges): array
    {
        $adj = [];
        foreach ($edges as $e) {
            if (! $e->from || ! $e->to) {
                continue;
            }
            $adj[$e->from][] = ['to' => $e->to, 'pipe' => $e->pipe];
            $adj[$e->to][] = ['to' => $e->from, 'pipe' => $e->pipe];
        }

        return $adj;
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

    /** Panjang streets LineString [lng,lat][] dalam meter. */
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

    private function emptyResult(string $note): array
    {
        return [
            'ok' => false, 'valves_to_close' => [], 'valves_closed' => [],
            'isolated_pipes' => [], 'isolated_nodes' => [], 'reached_sources' => [],
            'isolated_pipe_id_list' => [], 'note' => $note,
        ];
    }
}
