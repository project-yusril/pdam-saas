<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintTrack;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Complaint::with(['customer:id,full_name,customer_number'])
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('assigned_to'), fn ($q, $v) => $q->where('assigned_to', $v))
            ->when($request->input('priority'), fn ($q, $v) => $q->where('priority', $v))
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")
            ->orderByDesc('created_at');

        $paginator = $query->paginate($request->input('per_page', 25));

        return ApiResponse::paginated($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'category' => ['required', 'string', 'max:30'],
            'priority' => ['in:low,medium,high,urgent'],
            'subject' => ['required', 'string', 'max:500'],
            'description' => ['required', 'string'],
            'attachments' => ['nullable', 'array'],
        ]);

        $data['ticket_number'] = 'TKT-'.now()->format('ym').'-'.strtoupper(uniqid());
        $data['status'] = 'open';
        $data['sla_due_at'] = $this->calculateSla($data['priority'] ?? 'medium');

        $complaint = Complaint::create($data);

        ComplaintTrack::create([
            'pdam_org_id' => $complaint->pdam_org_id,
            'complaint_id' => $complaint->id,
            'to_status' => 'open',
            'action' => 'created',
            'user_id' => $request->user()->id,
        ]);

        return ApiResponse::success($complaint, status: 201);
    }

    public function show(Complaint $complaint): JsonResponse
    {
        $complaint->load(['customer', 'tracks', 'tracks.user:id,name']);

        return ApiResponse::success($complaint);
    }

    public function assign(Request $request, Complaint $complaint): JsonResponse
    {
        $data = $request->validate([
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ]);

        $complaint->update(['assigned_to' => $data['assigned_to'], 'status' => 'assigned']);

        ComplaintTrack::create([
            'pdam_org_id' => $complaint->pdam_org_id,
            'complaint_id' => $complaint->id,
            'to_status' => 'assigned',
            'action' => 'assigned',
            'user_id' => $request->user()->id,
        ]);

        return ApiResponse::success($complaint);
    }

    public function resolve(Request $request, Complaint $complaint): JsonResponse
    {
        $data = $request->validate(['resolution' => ['required', 'string']]);

        $complaint->update([
            'status' => 'resolved',
            'resolution' => $data['resolution'],
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ]);

        ComplaintTrack::create([
            'pdam_org_id' => $complaint->pdam_org_id,
            'complaint_id' => $complaint->id,
            'to_status' => 'resolved',
            'action' => 'resolved',
            'user_id' => $request->user()->id,
            'note' => $data['resolution'],
        ]);

        return ApiResponse::success($complaint);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $orgId = $request->user()->pdam_org_id;

        return ApiResponse::success([
            'open' => Complaint::where('pdam_org_id', $orgId)->where('status', 'open')->count(),
            'assigned' => Complaint::where('pdam_org_id', $orgId)->where('status', 'assigned')->count(),
            'overdue' => Complaint::where('pdam_org_id', $orgId)->where('sla_due_at', '<', now())->whereIn('status', ['open', 'assigned'])->count(),
            'resolved_today' => Complaint::where('pdam_org_id', $orgId)->where('status', 'resolved')->whereDate('resolved_at', today())->count(),
        ]);
    }

    private function calculateSla(string $priority): \DateTime
    {
        $hours = match ($priority) {
            'urgent' => 4,
            'high' => 8,
            'low' => 48,
            default => 24,
        };

        return now()->addHours($hours);
    }
}
