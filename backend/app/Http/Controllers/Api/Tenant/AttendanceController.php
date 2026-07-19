<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\HrEmployee;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\Shift;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    // ── ATTENDANCE ────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = Attendance::with('employee:id,name,nip')
            ->when($request->input('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->input('date'), fn ($q, $v) => $q->where('date', $v))
            ->when($request->input('date_from'), fn ($q, $v) => $q->where('date', '>=', $v))
            ->when($request->input('date_to'), fn ($q, $v) => $q->where('date', '<=', $v))
            ->orderByDesc('date');

        return ApiResponse::paginated($query->paginate($request->input('per_page', 31)));
    }

    public function checkIn(Request $request): JsonResponse
    {
        $employee = HrEmployee::where('user_id', $request->user()->id)->firstOrFail();

        $data = $request->validate([
            'date' => ['required', 'date'],
            'check_in' => ['required', 'date_format:H:i:s'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'source' => ['in:mobile,manual,machine'],
            'notes' => ['nullable', 'string'],
        ]);

        $attendance = Attendance::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $data['date']],
            [
                'pdam_org_id' => $employee->pdam_org_id,
                'check_in' => $data['check_in'],
                'check_in_lat' => $data['latitude'] ?? null,
                'check_in_lng' => $data['longitude'] ?? null,
                'status' => 'present',
                'source' => $data['source'] ?? 'mobile',
                'notes' => $data['notes'] ?? null,
            ]
        );

        return ApiResponse::success($attendance);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $employee = HrEmployee::where('user_id', $request->user()->id)->firstOrFail();

        $data = $request->validate([
            'date' => ['required', 'date'],
            'check_out' => ['required', 'date_format:H:i:s'],
        ]);

        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('date', $data['date'])
            ->first();

        if (! $attendance) {
            return ApiResponse::error('NOT_FOUND', 'Belum check-in.', null, 422);
        }

        $attendance->update(['check_out' => $data['check_out']]);

        return ApiResponse::success($attendance);
    }

    // ── LEAVE ─────────────────────────────────────────────────────────────
    public function leaves(Request $request): JsonResponse
    {
        $query = Leave::with(['employee:id,name,nip', 'leaveType:id,code,name'])
            ->when($request->input('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('start_date');

        return ApiResponse::paginated($query->paginate($request->input('per_page', 25)));
    }

    public function requestLeave(Request $request): JsonResponse
    {
        $employee = HrEmployee::where('user_id', $request->user()->id)->firstOrFail();

        $data = $request->validate([
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
        ]);

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $duration = $start->diffInWeekdays($end) + 1;

        $leaveType = LeaveType::find($data['leave_type_id']);

        $used = Leave::where('employee_id', $employee->id)
            ->where('leave_type_id', $data['leave_type_id'])
            ->where('status', 'approved')
            ->whereYear('start_date', now()->year)
            ->sum('duration_days');

        $remaining = $leaveType->default_quota - $used;

        if ($duration > $remaining) {
            return ApiResponse::error('INSUFFICIENT_QUOTA', "Sisa cuti {$leaveType->name}: {$remaining} hari.", null, 422);
        }

        $leave = Leave::create([
            'pdam_org_id' => $employee->pdam_org_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $data['leave_type_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'duration_days' => $duration,
            'balance_remaining' => $remaining - $duration,
            'status' => 'pending',
            'reason' => $data['reason'] ?? null,
        ]);

        return ApiResponse::success($leave, status: 201);
    }

    public function approveLeave(Request $request, Leave $leave): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected']]);

        $leave->update([
            'status' => $data['status'],
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        if ($data['status'] === 'approved') {
            $start = Carbon::parse($leave->start_date);
            $end = Carbon::parse($leave->end_date);

            while ($start->lte($end)) {
                if (! $start->isWeekend()) {
                    Attendance::updateOrCreate(
                        ['employee_id' => $leave->employee_id, 'date' => $start->toDateString()],
                        ['pdam_org_id' => $leave->pdam_org_id, 'status' => 'leave', 'source' => 'system']
                    );
                }
                $start->addDay();
            }
        }

        return ApiResponse::success($leave);
    }

    // ── OVERTIME ──────────────────────────────────────────────────────────
    public function overtimeRequests(Request $request): JsonResponse
    {
        $query = OvertimeRequest::with('employee:id,name,nip')
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('date');

        return ApiResponse::paginated($query->paginate($request->input('per_page', 25)));
    }

    public function requestOvertime(Request $request): JsonResponse
    {
        $employee = HrEmployee::where('user_id', $request->user()->id)->firstOrFail();

        $data = $request->validate([
            'date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $ot = OvertimeRequest::create([
            'pdam_org_id' => $employee->pdam_org_id,
            'employee_id' => $employee->id,
            'date' => $data['date'],
            'hours' => $data['hours'],
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);

        return ApiResponse::success($ot, status: 201);
    }

    public function approveOvertime(Request $request, OvertimeRequest $overtime): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected']]);

        $overtime->update([
            'status' => $data['status'],
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return ApiResponse::success($overtime);
    }

    // ── SHIFT ─────────────────────────────────────────────────────────────
    public function shifts(Request $request): JsonResponse
    {
        $query = Shift::when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        return ApiResponse::success($query->get());
    }

    public function createShift(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'start_time' => ['required', 'date_format:H:i:s'],
            'end_time' => ['required', 'date_format:H:i:s'],
            'is_overnight' => ['boolean'],
        ]);

        return ApiResponse::success(Shift::create($data), status: 201);
    }
}
