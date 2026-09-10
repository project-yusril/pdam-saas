<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Models\WorkOrderLog;
use App\Services\NotificationChannelService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkOrderController extends Controller
{
    public function __construct(private NotificationChannelService $notif) {}

    public function index(Request $request): JsonResponse
    {
        // mine=1 → layar mobile "WO Saya": hanya milik user yang login (id
        // tidak bisa dipalsukan utk mengintip WO orang lain).
        $query = WorkOrder::with(['assignedTo:id,name'])
            ->when($request->boolean('mine'), fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->input('assigned_to'), fn ($q, $v) => $q->where('assigned_to', $v))
            ->when($request->input('zone_id'), fn ($q, $v) => $q->where('zone_id', $v))
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")
            ->orderByDesc('created_at');

        $paginator = $query->paginate($request->input('per_page', 25));

        return ApiResponse::paginated($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:repair,installation,disconnect,reconnect,meter_change,inspection,leakage'],
            'priority' => ['in:low,medium,high,urgent'],
            'customer_id' => ['nullable', 'integer'],
            'zone_id' => ['nullable', 'integer'],
            'source_type' => ['nullable', 'string'],
            'source_id' => ['nullable', 'integer'],
            'address' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'description' => ['nullable', 'string'],
        ]);

        $data['wo_number'] = 'WO-'.now()->format('ym').'-'.strtoupper(uniqid());
        $data['status'] = 'open';

        $priority = $data['priority'] ?? 'medium';
        $data['sla_due_at'] = match ($priority) {
            'urgent' => now()->addHours(2),
            'high' => now()->addHours(6),
            'low' => now()->addHours(48),
            default => now()->addHours(24),
        };

        $wo = WorkOrder::create($data);

        WorkOrderLog::create([
            'pdam_org_id' => $wo->pdam_org_id,
            'work_order_id' => $wo->id,
            'to_status' => 'open',
            'action' => 'created',
            'user_id' => $request->user()->id,
        ]);

        return ApiResponse::success($wo, status: 201);
    }

    public function show(WorkOrder $workOrder): JsonResponse
    {
        $workOrder->load(['assignedTo', 'logs', 'logs.user:id,name']);

        return ApiResponse::success($workOrder);
    }

    public function assign(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $data = $request->validate(['assigned_to' => ['required', 'integer', 'exists:users,id']]);

        $workOrder->update(['assigned_to' => $data['assigned_to'], 'status' => 'assigned']);

        WorkOrderLog::create([
            'pdam_org_id' => $workOrder->pdam_org_id,
            'work_order_id' => $workOrder->id,
            'to_status' => 'assigned',
            'action' => 'assigned',
            'user_id' => $request->user()->id,
        ]);

        $this->notif->send($data['assigned_to'], 'WO Baru', "WO #{$workOrder->wo_number} ditugaskan ke Anda.", ['push']);

        return ApiResponse::success($workOrder);
    }

    public function start(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $workOrder->update(['status' => 'in_progress', 'started_at' => now()]);

        WorkOrderLog::create([
            'pdam_org_id' => $workOrder->pdam_org_id,
            'work_order_id' => $workOrder->id,
            'to_status' => 'in_progress',
            'action' => 'started',
            'user_id' => $request->user()->id,
        ]);

        return ApiResponse::success($workOrder);
    }

    public function complete(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $data = $request->validate([
            'resolution' => ['required', 'string'],
            'photos_after' => ['nullable', 'array'],
            'customer_signature' => ['boolean'],
        ]);

        $workOrder->update([
            'status' => 'completed',
            'completed_at' => now(),
            'resolution' => $data['resolution'],
            'photos_after' => $data['photos_after'] ?? $workOrder->photos_after,
            'customer_signature' => $data['customer_signature'] ?? false,
        ]);

        WorkOrderLog::create([
            'pdam_org_id' => $workOrder->pdam_org_id,
            'work_order_id' => $workOrder->id,
            'to_status' => 'completed',
            'action' => 'completed',
            'note' => $data['resolution'],
            'user_id' => $request->user()->id,
        ]);

        return ApiResponse::success($workOrder);
    }

    public function technicianDashboard(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        return ApiResponse::success([
            'pending' => WorkOrder::where('assigned_to', $userId)->where('status', 'assigned')->count(),
            'in_progress' => WorkOrder::where('assigned_to', $userId)->where('status', 'in_progress')->count(),
            'completed_today' => WorkOrder::where('assigned_to', $userId)->where('status', 'completed')->whereDate('completed_at', today())->count(),
            'overdue' => WorkOrder::where('assigned_to', $userId)->whereIn('status', ['assigned', 'in_progress'])->where('sla_due_at', '<', now())->count(),
        ]);
    }
}
