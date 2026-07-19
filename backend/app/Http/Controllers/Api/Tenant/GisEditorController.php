<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\GisFeature;
use App\Models\GisNetworkEdge;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GisEditorController extends Controller
{
    public function pipes(Request $request): JsonResponse
    {
        $query = GisFeature::where('feature_type', 'pipe')
            ->when($request->input('zone_id'), fn ($q, $v) => $q->where('zone_id', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v));

        return ApiResponse::paginated($query->paginate($request->input('per_page', 100)));
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
            'properties' => $data['properties'] ?? [
                'diameter' => null,
                'material' => 'PVC',
                'length_meters' => null,
                'install_year' => now()->year,
                'status' => 'aktif',
            ],
            'zone_id' => $data['zone_id'] ?? null,
        ]);

        return ApiResponse::success($feature, status: 201);
    }

    public function updatePipe(Request $request, GisFeature $pipe): JsonResponse
    {
        $data = $request->validate([
            'name' => ['string', 'max:200'],
            'geometry' => ['array'],
            'properties' => ['array'],
            'status' => ['in:aktif,rusak,rencana'],
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
}
