<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DmaZone;
use App\Models\GisFeature;
use App\Models\GisNetworkEdge;
use App\Models\MeterRoute;
use App\Models\NrwBalance;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLog;
use App\Models\Zone;
use App\Services\Geo\FieldLocationService;
use App\Services\Geo\NetworkGraphService;
use App\Services\Geo\NrwAnalysisService;
use App\Services\Geo\OsrmService;
use App\Services\NotificationChannelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * NetworkWebController — GIS Jaringan Perpipaan lengkap untuk admin/direktur/teknis:
 *  - editor peta jaringan (gambar pipa LineString, node valve/hydrant/pump/reservoir, polygon DMA)
 *  - isolasi kebocoran: hitung valve yang harus ditutup + segmen mati + pelanggan terdampak
 *  - insiden → WorkOrder (tipe leakage/repair) ter-link fitur GIS
 *  - analisis NRW per DMA otomatik dari tagihan + distribusi reading
 *  - petugas lapangan LIVE di peta (dari GPS aplikasi mobile) → dispatch WO
 */
class NetworkWebController extends Controller
{
    public function __construct(
        private NetworkGraphService $graph,
        private NrwAnalysisService $nrw,
        private FieldLocationService $officers,
        private OsrmService $osrm,
        private NotificationChannelService $notify,
    ) {}

    // ── Halaman utama ──────────────────────────────────────────────────────

    public function index(): View
    {
        return view('admin.network.index', [
            'dmas' => DmaZone::orderBy('code')->get(['id', 'code', 'name', 'zone_id', 'is_active']),
            'zones' => Zone::orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    // ── Data lapisan (GeoJSON) ─────────────────────────────────────────────

    /** Satu endpoint utk semua layer: pipa, node, edge (＋ arah aliran), DMA. */
    public function layers(): JsonResponse
    {
        $pipes = GisFeature::where('feature_type', 'pipe')
            ->get(['id', 'name', 'geometry', 'properties', 'status', 'zone_id'])
            ->map(fn ($f) => $this->toFeature($f, 'pipe'))->values();
        $nodes = GisFeature::where('feature_type', '!=', 'pipe')
            ->whereIn('feature_type', ['valve', 'junction', 'hydrant', 'pump', 'reservoir', 'intake', 'treatment'])
            ->get(['id', 'name', 'geometry', 'properties', 'status', 'zone_id'])
            ->map(fn ($f) => $this->toFeature($f, 'node'))->values();
        $orientations = $this->graph->edgeOrientations();
        $edges = GisNetworkEdge::with(['fromFeature:id,feature_type', 'toFeature:id,feature_type'])
            ->get()
            ->map(fn (GisNetworkEdge $e) => [
                'id' => $e->id,
                'pipe_feature_id' => $e->pipe_feature_id,
                'from' => ['id' => $e->from_node_id, 'type' => $e->fromFeature?->feature_type],
                'to' => ['id' => $e->to_node_id, 'type' => $e->toFeature?->feature_type],
                'length_meters' => $e->length_meters !== null ? (float) $e->length_meters : null,
                // arah aliran hasil BFS sumber (hulu→hilir) — untuk panah di peta.
                'flow' => isset($orientations[$e->id])
                    ? ['from' => $orientations[$e->id]['from'], 'to' => $orientations[$e->id]['to']]
                    : null,
            ])->values();
        $dmas = DmaZone::whereNotNull('boundary')->where('boundary', '!=', '[]')
            ->get(['id', 'code', 'name', 'boundary'])
            ->map(fn ($d) => $this->toFeature(new GisFeature([
                'feature_type' => 'dma', 'name' => $d->name ?? $d->code,
                'geometry' => $d->boundary, 'status' => null,
            ]), 'dma', $d->id))->values();

        return response()->json([
            'type' => 'FeatureCollection',
            'pipes' => $pipes,
            'nodes' => $nodes,
            'edges' => $edges,
            'dmas' => $dmas,
        ]);
    }

    private function toFeature($f, string $role, ?int $id = null): array
    {
        return [
            'type' => 'Feature',
            'id' => $id ?? ($f->id ?? null),
            'role' => $role,
            'geometry' => $f->geometry,
            'properties' => array_merge($f->properties ?? [], [
                'feature_id' => $id ?? $f->id,
                'feature_type' => $f->feature_type,
                'name' => $f->name,
                'status' => $f->status,
                'zone_id' => $f->zone_id,
            ]),
        ];
    }

    // ── Editor: pipa & node ────────────────────────────────────────────────

    public function storeFeature(Request $request): JsonResponse
    {
        $data = $request->validate([
            'feature_type' => ['required', 'in:pipe,valve,junction,hydrant,pump,reservoir,intake,treatment'],
            'name' => ['nullable', 'string', 'max:200'],
            'geometry' => ['required', 'array'],
            'geometry.type' => ['required', 'in:LineString,Point'],
            'geometry.coordinates' => ['required', 'array', 'min:2'],
            'properties' => ['nullable', 'array'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
        ]);

        if ($data['feature_type'] === 'pipe') {
            return $this->storePipe($data);
        }

        // Point harus tunggal
        if (($data['geometry']['type'] ?? 'Point') === 'LineString') {
            return response()->json(['error' => 'Node harus berupa titik (Point).'], 422);
        }

        $feature = GisFeature::create([
            'pdam_org_id' => $request->user()->pdam_org_id,
            'feature_type' => $data['feature_type'],
            'name' => $data['name'] ?? $this->defaultName($data['feature_type']),
            'geometry' => $data['geometry'],
            'properties' => $data['properties'] ?? [],
            'zone_id' => $data['zone_id'] ?? null,
            'status' => 'active',
        ]);

        return response()->json($feature, 201);
    }

    /** Simpan pipa + auto-wire edges ke node / junction baru. */
    private function storePipe(array $data): JsonResponse
    {
        $coordinates = $data['geometry']['coordinates'] ?? [];
        if (count($coordinates) < 2) {
            return response()->json(['error' => 'Garis pipa minimal 2 titik.'], 422);
        }

        $pipe = GisFeature::create([
            'pdam_org_id' => request()->user()->pdam_org_id,
            'feature_type' => 'pipe',
            'name' => $data['name'] ?? $this->defaultName('pipe'),
            'geometry' => ['type' => 'LineString', 'coordinates' => $coordinates],
            'properties' => array_merge([
                'diameter_mm' => null,
                'material' => 'HDPE',
                'install_year' => (int) date('Y'),
            ], $data['properties'] ?? [], [
                'length_meters' => round($this->graph->lineLengthMeters($coordinates), 2),
            ]),
            'zone_id' => $data['zone_id'] ?? null,
            'status' => 'active',
        ]);

        $nodes = GisFeature::whereNotIn('feature_type', ['pipe'])->get();
        $wiring = $this->graph->ensureEndpoints($pipe, $nodes);
        $pipe->update(['properties' => array_merge($pipe->properties, ['auto_connected' => true])]);

        return response()->json(['pipe' => $pipe, 'wiring' => $wiring], 201);
    }

    public function updateFeature(Request $request, GisFeature $feature): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'status' => ['sometimes', 'in:active,rusak,rencana,closed'],
            'geometry' => ['sometimes', 'array'],
            'properties' => ['sometimes', 'array'],
        ]);

        if (isset($data['geometry'])) {
            $new = $data['geometry'];
            $ok = ($new['type'] ?? null) === $feature->geometry['type']
                && is_array($new['coordinates'] ?? null);
            if (! $ok) {
                return response()->json(['error' => 'Bentuk geometry tidak berubah / koordinat kosong.'], 422);
            }
            if ($feature->feature_type === 'pipe') {
                $data['properties'] = array_merge(
                    (array) ($data['properties'] ?? $feature->properties ?? []),
                    ['length_meters' => round($this->graph->lineLengthMeters($new['coordinates']), 2)]
                );
            }
        }

        if (isset($data['status']) && $data['status'] === 'closed' && $feature->feature_type !== 'valve') {
            return response()->json(['error' => 'Hanya valve yang boleh berstatus closed.'], 422);
        }

        $feature->update($data);

        return response()->json($feature);
    }

    public function destroyFeature(Request $request, GisFeature $feature): JsonResponse
    {
        $edges = $this->graph->deleteFeatureWithEdges($feature);

        return response()->json([
            'message' => ($feature->feature_type ?? 'Fitur').' terhapus.',
            'deleted_edges' => $edges,
        ]);
    }

    // ── Edge: tambah sambungan manual ──────────────────────────────────────

    public function storeEdge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pipe_feature_id' => ['nullable', 'integer', 'exists:gis_features,id'],
            'from_node_id' => ['required', 'integer', 'exists:gis_features,id'],
            'to_node_id' => ['required', 'integer', 'different:from_node_id', 'exists:gis_features,id'],
        ]);

        $exists = GisNetworkEdge::where('from_node_id', $data['from_node_id'])
            ->where('to_node_id', $data['to_node_id'])->exists();
        if ($exists) {
            return response()->json(['error' => 'Sambungan sudah ada.'], 422);
        }

        $from = GisFeature::findOrFail($data['from_node_id']);
        $to = GisFeature::findOrFail($data['to_node_id']);

        $length = null;
        if (! empty($data['pipe_feature_id'])) {
            $length = GisFeature::find($data['pipe_feature_id']);
            $length = $length ? round($this->graph->lineLengthMeters($length->geometry['coordinates'] ?? []), 2) : null;
        }

        $edge = GisNetworkEdge::create([
            'pdam_org_id' => $request->user()->pdam_org_id,
            'pipe_feature_id' => $data['pipe_feature_id'] ?? null,
            'from_node_id' => $from->id,
            'from_node_type' => $from->feature_type,
            'to_node_id' => $to->id,
            'to_node_type' => $to->feature_type,
            'length_meters' => $length,
        ]);

        return response()->json($edge, 201);
    }

    public function destroyEdge(Request $request, GisNetworkEdge $edge): JsonResponse
    {
        if ($edge->pipe_feature_id) {
            GisFeature::find($edge->pipe_feature_id)?->delete();
        }
        $edge->delete();

        return response()->json(['message' => 'Sambungan (+ pipa) dihapus.']);
    }

    // ── DMA zone ───────────────────────────────────────────────────────────

    public function storeDma(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:150'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'boundary' => ['required', 'array'],
            'boundary.0.0' => ['required', 'numeric'], // ada minimal 1 titik ring
        ]);

        $dma = DmaZone::create([
            'pdam_org_id' => $request->user()->pdam_org_id,
            'code' => $data['code'],
            'name' => $data['name'],
            'zone_id' => $data['zone_id'] ?? null,
            'boundary' => ['type' => 'Polygon', 'coordinates' => [$data['boundary']]],
            'total_connections' => 0,
            'base_demand_m3day' => $request->input('base_demand_m3day', 0),
            'is_active' => true,
        ]);

        return response()->json($dma, 201);
    }

    public function updateDma(Request $request, DmaZone $dma): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:30'],
            'name' => ['sometimes', 'string', 'max:150'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'is_active' => ['sometimes', 'boolean'],
            'base_demand_m3day' => ['sometimes', 'numeric', 'min:0'],
        ]);
        $dma->update($data);

        return response()->json($dma);
    }

    public function destroyDma(Request $request, DmaZone $dma): JsonResponse
    {
        $dma->delete();

        return response()->json(['message' => 'DMA dihapus.']);
    }

    // ── Kebocoran: isolasi + pelanggan + work order ────────────────────────

    public function isolate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'feature_id' => ['required', 'integer'],
        ]);

        if (! GisFeature::find($data['feature_id'])) {
            return response()->json(['error' => 'Fitur tidak ditemukan.'], 404);
        }

        $result = $this->graph->isolate($data['feature_id']);
        if (! $result['ok']) {
            return response()->json($result, 422);
        }

        $region = $this->graph->affectedCustomersInRegion($result['isolated_pipe_id_list']);

        return response()->json($this->stripKeys($result) + [
            'affected' => $region,
        ]);
    }

    private function stripKeys(array $r): array
    {
        unset($r['isolated_pipe_id_list']);

        return $r;
    }

    /** Insiden pipa bocor: buat WorkOrder `leakage` otomatis dari fitur GIS. */
    public function storeIncidentWorkOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'feature_id' => ['required', 'integer'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $feature = GisFeature::find($data['feature_id']);
        if (! $feature) {
            return response()->json(['error' => 'Fitur tidak ditemukan.'], 404);
        }
        $coords = $this->firstCoordinate($feature);
        if ($coords === []) {
            return response()->json(['error' => 'Fitur tidak punya koordinat.'], 422);
        }

        [$wo] = $this->createNetworkWorkOrder(
            $request, $feature, (float) $coords['lat'], (float) $coords['lng'],
            $data['priority'] ?? 'urgent', $data['assigned_to'] ?? null,
            $data['description'] ?? null,
        );

        return response()->json($wo, 201);
    }

    /**
     * Inti pembuatan WO jaringan + pipa ditandai rusak + log. Dipakai tombol
     * "Insiden → WO" dan "Dispatch petugas terdekat" (satu jalur audit).
     *
     * @param  GisFeature|null  $feature
     * @return array{0:WorkOrder,1:WorkOrderLog}
     */
    private function createNetworkWorkOrder(
        Request $request,
        ?GisFeature $feature,
        float $lat,
        float $lng,
        string $priority,
        ?int $assignedTo,
        ?string $description = null
    ): array {
        $sla = ['urgent' => 2, 'high' => 6, 'low' => 48];
        $slaHours = $sla[$priority] ?? 24;
        $wo = WorkOrder::create([
            'pdam_org_id' => $request->user()->pdam_org_id,
            'wo_number' => 'WO-'.now()->format('ym').'-NET'.strtoupper(substr(uniqid(), -4)),
            'type' => ($feature && $feature->feature_type === 'pipe') ? 'repair' : ($assignedTo ? 'leakage' : 'inspection'),
            'priority' => $priority,
            'zone_id' => $feature?->zone_id,
            'source_type' => $feature ? 'gis_feature' : null,
            'source_id' => $feature?->id,
            'address' => 'Jaringan perpipaan — '.$this->incidentLabel($feature, $lat, $lng),
            'latitude' => $lat,
            'longitude' => $lng,
            'description' => $description ?? 'Insiden jaringan — dibuat dari dashboard GIS.',
            'status' => $assignedTo ? 'assigned' : 'open',
            'assigned_to' => $assignedTo,
            'sla_due_at' => now()->addHours($slaHours),
        ]);

        $toStatus = $assignedTo ? 'assigned' : 'open';
        $log = WorkOrderLog::create([
            'pdam_org_id' => $wo->pdam_org_id,
            'work_order_id' => $wo->id,
            'to_status' => $toStatus,
            'action' => $assignedTo ? 'dispatched' : 'created',
            'user_id' => $request->user()->id,
        ]);

        if ($assignedTo) {
            $this->notify->send(
                $assignedTo,
                'WO Baru (GIS)',
                "WO {$wo->wo_number} di-dispatch dari peta jaringan ke lokasi Anda.",
                ['in_app', 'push']
            );
        }

        if ($feature && $feature->feature_type === 'pipe' && $feature->status !== 'rusak') {
            $feature->update(['status' => 'rusak']);
        }

        return [$wo, $log];
    }

    /** Ambil titik insiden dari feature_id ATAU lat/lng manual (untuk dispatch). */
    private function incidentPoint(Request $request, array $data): array
    {
        if (! empty($data['feature_id'])) {
            $feature = GisFeature::find($data['feature_id']);
            if (! $feature) {
                return [null, null, null];
            }
            $coords = $this->firstCoordinate($feature);

            return [
                isset($coords['lat']) ? (float) $coords['lat'] : (isset($data['lat']) ? (float) $data['lat'] : null),
                isset($coords['lng']) ? (float) $coords['lng'] : (isset($data['lng']) ? (float) $data['lng'] : null),
                $feature,
            ];
        }
        if (isset($data['lat'], $data['lng'])) {
            return [(float) $data['lat'], (float) $data['lng'], null];
        }

        return [null, null, null];
    }

    private function incidentLabel(?GisFeature $feature, float $lat, float $lng): string
    {
        if ($feature && $feature->name) {
            return $feature->name;
        }

        return 'titik '.round($lat, 5).','.round($lng, 5);
    }

    /** Daftar petugas yang bisa di-assign (bukan sekadar user lain). */
    public function officers(Request $request): JsonResponse
    {
        $candidates = User::query()
            ->where('is_active', true)
            ->with('roles:id,code')
            ->get(['id', 'name'])
            ->filter(fn ($u) => (bool) $u->roles->whereIn('code', [
                'field_technician', 'maintenance_technician', 'installer_technician',
                'field_dispatcher', 'technical_head',
            ])->isNotEmpty())
            ->values();

        return response()->json(['items' => $candidates]);
    }

    /** Titik petugas LIVE di peta (dari GPS aplikasi mobile) + status online. */
    public function technicians(Request $request): JsonResponse
    {
        $orgId = (int) $request->user()->pdam_org_id;

        return response()->json([
            'stale_minutes' => $this->officers->staleMinutes(),
            'items' => $this->officers->activeOfficers($orgId),
        ]);
    }

    /**
     * Dispatch: dari fitur/lokasi insiden → carikan petugas ONLINE terdekat
     * (haversine) + rute OSRM, buat WorkOrder ter-assign satu klik.
     * Integrasi GIS↔mobile 2-arah: GPS nyata masuk peta → tombol keluar WO.
     */
    public function dispatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'feature_id' => ['nullable', 'integer'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'priority' => ['sometimes', 'in:low,high,urgent'],
        ]);

        [$lat, $lng, $feature] = $this->incidentPoint($request, $data);
        if ($lat === null) {
            return response()->json(['error' => 'Titik insiden tidak diketahui (pilih fitur atau kirim lat/lng).'], 422);
        }

        $nearest = $this->officers->nearestOfficer($request->user()->pdam_org_id, $lat, $lng);
        if (! $nearest) {
            return response()->json([
                'error' => 'Tidak ada petugas online dalam 24 jam terakhir. Lapor lokasi via mobile dahulu.',
                'candidates' => $this->officers->rankedOfficers($request->user()->pdam_org_id, $lat, $lng)
                    ->take(5)->values(),
            ], 422);
        }

        [$wo, $log] = $this->createNetworkWorkOrder(
            $request, $feature, $lat, $lng,
            $data['priority'] ?? 'urgent',
            $nearest['user_id'],
        );

        $route = $this->osrm->route(
            [[$nearest['lat'], $nearest['lng']], [$lat, $lng]],
            'driving'
        );

        return response()->json([
            'ok' => true,
            'work_order' => $wo,
            'officer' => $nearest + ['user_id' => $nearest['user_id']],
            'route' => $route ? [
                'geometry' => $route['geometry'],
                'distance_m' => round($route['distance'], 1),
                'duration_s' => round($route['duration'], 0),
                'from' => ['lat' => $nearest['lat'], 'lng' => $nearest['lng']],
                'to' => ['lat' => $lat, 'lng' => $lng],
            ] : null,
            'note' => 'Rute OSRM dari petugas → insiden. Dispatch '
                . ($nearest['name'] ?? '#'.$nearest['user_id'])
                . ' ('.round($nearest['distance_m']).' m lurus).',
        ], 201);
    }

    // ── NRW ────────────────────────────────────────────────────────────────

    public function nrwSummary(Request $request): JsonResponse
    {
        $period = $request->input('period') ?: now()->startOfMonth()->subDay()->format('Y-m');

        return response()->json([
            'period' => $period,
            'dmas' => $this->nrw->summary($period),
        ]);
    }

    public function nrwCalculate(Request $request, DmaZone $dma): JsonResponse
    {
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        $result = $this->nrw->calculate($dma, $data['period']);

        return $result['ok']
            ? response()->json([
                'ok' => true,
                'period' => $data['period'],
                'dma' => ['id' => $dma->id, 'code' => $dma->code, 'name' => $dma->name],
                'supply_m3' => $result['supply_m3'],
                'billed_m3' => $result['billed_m3'],
                'nrw_percent' => $result['nrw_percent'],
                'balance_id' => $result['balance']->id ?? null,
            ])
            : response()->json(['error' => $result['error']], 422);
    }

    public function nrwDetail(Request $request, NrwBalance $balance): JsonResponse
    {
        return response()->json($balance);
    }

    // ─ util ────────────────────────────────────────────────────────────────

    private function defaultName(string $type): string
    {
        $prefix = match ($type) {
            'pipe' => 'PIPE', 'valve' => 'VLV', 'hydrant' => 'HYD',
            'pump' => 'PUMP', 'reservoir' => 'RES', 'junction' => 'JCT',
            default => strtoupper($type),
        };

        return $prefix.'-'.strtoupper(substr(uniqid((string) mt_rand(), true), -6));
    }

    private function firstCoordinate(GisFeature $feature): array
    {
        $c = $feature->geometry['coordinates'] ?? [];

        // Drill ke koordinat mentah pertama: Point [lng,lat], LineString [[lng,lat],...]
        // Polygon [[[lng,lat],...]].
        while (is_array($c) && isset($c[0]) && is_array($c[0])) {
            $c = $c[0];
        }
        if (is_array($c) && count($c) >= 2) {
            return ['lat' => (float) $c[1], 'lng' => (float) $c[0]];
        }

        return [];
    }

    /** Info rute baca utk editor (opsional, dipakai tombol "rute → suplai" nanti). */
    public function routes(): JsonResponse
    {
        return response()->json([
            'items' => MeterRoute::orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }
}
