<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\DmaZone;
use App\Models\GisFeature;
use App\Models\GisNetworkEdge;
use App\Models\WorkOrder;
use App\Models\WorkOrderLog;
use App\Services\Geo\NetworkGraphService;
use App\Services\Geo\NrwAnalysisService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GisEditorController extends Controller
{
    public function __construct(
        private NetworkGraphService $graph,
        private NrwAnalysisService $nrwService,
    ) {}

    public function pipes(Request $request): JsonResponse
    {
        $query = GisFeature::where('feature_type', 'pipe')
            ->when($request->input('zone_id'), fn ($q, $v) => $q->where('zone_id', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v));

        // per_page boleh besar utk render jaringan penuh di web/SPA (cap aman).
        $perPage = min(max((int) $request->input('per_page', 100), 10), 8000);

        return ApiResponse::paginated($query->paginate($perPage)->withQueryString());
    }

    public function createPipe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:200'],
            'geometry' => ['required', 'array'],
            'geometry.type' => ['required', 'in:LineString'],
            'geometry.coordinates' => ['required', 'array', 'min:2'],
            'properties' => ['nullable', 'array'],
            'zone_id' => ['nullable', 'integer'],
        ]);

        $feature = GisFeature::create([
            'pdam_org_id' => $request->user()->pdam_org_id,
            'feature_type' => 'pipe',
            'name' => $data['name'] ?? null,
            'geometry' => $data['geometry'],
            'properties' => array_merge([
                'diameter' => null,
                'material' => 'PVC',
                'install_year' => now()->year,
            ], $data['properties'] ?? [], [
                'length_meters' => round($this->graph->lineLengthMeters($data['geometry']['coordinates']), 2),
            ]),
            'zone_id' => $data['zone_id'] ?? null,
            'status' => 'active',
        ]);

        // Auto-wire: sambungkan ujung pipa ke node terdekat / buat junction baru.
        $nodes = GisFeature::whereNotIn('feature_type', ['pipe', 'dma'])->get();
        $wiring = $this->graph->ensureEndpoints($feature, $nodes);

        return ApiResponse::success($feature->fresh()->toArray() + ['wiring' => $wiring], status: 201);
    }

    public function updatePipe(Request $request, GisFeature $pipe): JsonResponse
    {
        $data = $request->validate([
            'name' => ['string', 'max:200'],
            'geometry' => ['array'],
            'properties' => ['array'],
            'status' => ['in:active,aktif,rusak,rencana,closed'],
        ]);
        $pipe->update($data);

        return ApiResponse::success($pipe);
    }

    public function features(Request $request): JsonResponse
    {
        $query = GisFeature::when($request->input('feature_type'), fn ($q, $v) => $q->where('feature_type', $v))
            ->when($request->input('zone_id'), fn ($q, $v) => $q->where('zone_id', $v))
            ->when($request->has('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->limit(1000);

        return ApiResponse::paginated($query->paginate($request->input('per_page', 500)));
    }

    public function createFeature(Request $request): JsonResponse
    {
        $data = $request->validate([
            'feature_type' => ['required', 'in:pipe,valve,hydrant,reservoir,pump,incident'],
            'name' => ['nullable', 'string', 'max:200'],
            'geometry' => ['required', 'array'],
            'properties' => ['nullable', 'array'],
            'zone_id' => ['nullable', 'integer'],
        ]);

        return ApiResponse::success(GisFeature::create(['pdam_org_id' => $request->user()->pdam_org_id, ...$data]), status: 201);
    }

    public function networkEdges(Request $request): JsonResponse
    {
        $query = GisNetworkEdge::with(['fromFeature:id,name,feature_type', 'toFeature:id,name,feature_type'])
            ->when($request->input('pipe_feature_id'), fn ($q, $v) => $q->where('pipe_feature_id', $v));

        return ApiResponse::paginated($query->paginate($request->input('per_page', 500)));
    }

    public function createEdge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pipe_feature_id' => ['required', 'integer', 'exists:gis_features,id'],
            'from_node_id' => ['required', 'integer', 'exists:gis_features,id'],
            'from_node_type' => ['required', 'string'],
            'to_node_id' => ['required', 'integer', 'different:from_node_id', 'exists:gis_features,id'],
            'to_node_type' => ['required', 'string'],
            'length_meters' => ['nullable', 'numeric', 'min:0'],
        ]);

        return ApiResponse::success(GisNetworkEdge::create(['pdam_org_id' => $request->user()->pdam_org_id, ...$data]), status: 201);
    }

    public function destroyFeature(Request $request, GisFeature $feature): JsonResponse
    {
        $deletedEdges = $this->graph->deleteFeatureWithEdges($feature);

        return ApiResponse::success(['deleted' => true, 'edges_deleted' => $deletedEdges]);
    }

    /** Analisis isolasi: valve mana yang harus ditutup utk sebuah ruas pipa/node. */
    public function isolate(Request $request): JsonResponse
    {
        $data = $request->validate(['feature_id' => ['required', 'integer']]);

        if (! GisFeature::find($data['feature_id'])) {
            return ApiResponse::error('NOT_FOUND', 'Fitur tidak ditemukan.', null, 404);
        }

        $result = $this->graph->isolate($data['feature_id']);
        if (! $result['ok']) {
            return ApiResponse::error('NOT_ISOLABLE', $result['note'], null, 422);
        }

        $region = $this->graph->affectedCustomersInRegion($result['isolated_pipe_id_list']);

        return ApiResponse::success($result + ['affected' => $region]);
    }

    /** Insiden pipa → WorkOrder emergency (source_type gis_feature). */
    public function storeIncident(Request $request): JsonResponse
    {
        $data = $request->validate([
            'feature_id' => ['required', 'integer', 'exists:gis_features,id'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $feature = GisFeature::findOrFail($data['feature_id']);
        $priority = $data['priority'] ?? 'urgent';

        $wo = WorkOrder::create([
            'pdam_org_id' => $request->user()->pdam_org_id,
            'wo_number' => 'WO-'.now()->format('ym').'-'.strtoupper(uniqid()),
            'type' => $feature->feature_type === 'pipe' ? 'repair' : 'inspection',
            'priority' => $priority,
            'zone_id' => $feature->zone_id,
            'source_type' => 'gis_feature',
            'source_id' => $feature->id,
            'address' => 'Jaringan — '.($feature->name ?? '#'.$feature->id),
            'description' => $data['description'] ?? 'Insiden jaringan dari dashboard GIS.',
            'status' => 'open',
            'sla_due_at' => now()->addHours(['urgent' => 2, 'high' => 6, 'low' => 48][$priority] ?? 24),
        ]);
        WorkOrderLog::create([
            'pdam_org_id' => $wo->pdam_org_id, 'work_order_id' => $wo->id,
            'to_status' => 'open', 'action' => 'created', 'user_id' => $request->user()->id,
        ]);
        if ($feature->feature_type === 'pipe' && $feature->status !== 'rusak') {
            $feature->update(['status' => 'rusak']);
        }

        return ApiResponse::success($wo, status: 201);
    }

    // ── DMA ────────────────────────────────────────────────────────────────

    public function dmas(Request $request): JsonResponse
    {
        return ApiResponse::paginated(DmaZone::orderBy('code')->paginate($request->input('per_page', 50)));
    }

    public function createDma(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:150'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'boundary' => ['required', 'array'],
            'boundary.0.0' => ['required', 'numeric'],
            'base_demand_m3day' => ['nullable', 'numeric', 'min:0'],
        ]);

        $dma = DmaZone::create([
            'pdam_org_id' => $request->user()->pdam_org_id,
            'code' => $data['code'], 'name' => $data['name'],
            'zone_id' => $data['zone_id'] ?? null,
            'boundary' => ['type' => 'Polygon', 'coordinates' => [$data['boundary']]],
            'total_connections' => 0,
            'base_demand_m3day' => $data['base_demand_m3day'] ?? 0,
            'is_active' => true,
        ]);

        return ApiResponse::success($dma, status: 201);
    }

    public function updateDma(Request $request, DmaZone $dma): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:30'],
            'name' => ['sometimes', 'string', 'max:150'],
            'zone_id' => ['nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'base_demand_m3day' => ['sometimes', 'numeric', 'min:0'],
        ]);
        $dma->update($data);

        return ApiResponse::success($dma);
    }

    // ── NRW auto (dashboard GIS mengambil dari hasil pipeline, beda dgn POST /nrw/calculate manual)

    public function autoNrw(Request $request, DmaZone $dma): JsonResponse
    {
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-\d{2}$/']]);

        $result = $this->nrwService->calculate($dma, $data['period']);
        if (! $result['ok']) {
            return ApiResponse::error('NO_DATA', $result['error'], null, 422);
        }

        return ApiResponse::success($result['balance']);
    }
}
