<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Complaint;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallCenterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CallLog::with('agent:id,name')
            ->when($request->input('direction'), fn ($q, $v) => $q->where('direction', $v))
            ->when($request->input('agent_id'), fn ($q, $v) => $q->where('agent_id', $v))
            ->orderByDesc('start_time');

        return ApiResponse::paginated($query->paginate($request->input('per_page', 25)));
    }

    public function log(Request $request): JsonResponse
    {
        $data = $request->validate([
            'call_id' => ['nullable', 'string', 'max:50'],
            'direction' => ['required', 'in:inbound,outbound'],
            'caller_number' => ['required', 'string', 'max:20'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'customer_id' => ['nullable', 'integer'],
            'disposition' => ['nullable', 'in:resolved,create_ticket,callback,escalate'],
            'notes' => ['nullable', 'string'],
            'create_ticket' => ['nullable', 'array'],
            'create_ticket.category' => ['required_if:create_ticket,array', 'string'],
            'create_ticket.subject' => ['required_if:create_ticket,array', 'string'],
            'create_ticket.description' => ['required_if:create_ticket,array', 'string'],
        ]);

        $callLog = CallLog::create([
            'pdam_org_id' => $request->user()->pdam_org_id,
            'call_id' => $data['call_id'] ?? null,
            'direction' => $data['direction'],
            'caller_number' => $data['caller_number'],
            'start_time' => now(),
            'end_time' => isset($data['duration_seconds']) ? now()->addSeconds($data['duration_seconds']) : now(),
            'duration_seconds' => $data['duration_seconds'] ?? 0,
            'agent_id' => $request->user()->id,
            'customer_id' => $data['customer_id'] ?? null,
            'disposition' => $data['disposition'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'completed',
        ]);

        $ticket = null;
        if (! empty($data['create_ticket'])) {
            $ticket = Complaint::create([
                'pdam_org_id' => $request->user()->pdam_org_id,
                'customer_id' => $data['customer_id'] ?? null,
                'ticket_number' => 'TKT-'.now()->format('ym').'-'.strtoupper(uniqid()),
                'category' => $data['create_ticket']['category'],
                'subject' => $data['create_ticket']['subject'],
                'description' => $data['create_ticket']['description'],
                'priority' => 'medium',
                'status' => 'open',
                'sla_due_at' => now()->addHours(24),
            ]);

            $callLog->update(['complaint_id' => $ticket->id]);
        }

        return ApiResponse::success([
            'call_log' => $callLog,
            'ticket' => $ticket,
        ], status: 201);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $today = CallLog::whereDate('start_time', today());

        return ApiResponse::success([
            'calls_today' => $today->count(),
            'inbound' => (clone $today)->where('direction', 'inbound')->count(),
            'outbound' => (clone $today)->where('direction', 'outbound')->count(),
            'avg_duration' => round($today->avg('duration_seconds') ?? 0, 0),
            'disposition_summary' => $today->selectRaw('disposition, COUNT(*) as count')->groupBy('disposition')->pluck('count', 'disposition'),
        ]);
    }
}
