<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceSchedule;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function schedules(Request $request): JsonResponse
    {
        $query = MaintenanceSchedule::when($request->input('asset_type'), fn ($q, $v) => $q->where('asset_type', $v))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('next_due_date');

        $paginator = $query->paginate($request->input('per_page', 25));

        return ApiResponse::paginated($paginator);
    }

    public function createSchedule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
            'asset_type' => ['required', 'string', 'max:30'],
            'asset_id' => ['nullable', 'integer'],
            'frequency' => ['required', 'in:daily,weekly,monthly,quarterly,semiannual,annual,meter_hours'],
            'interval_value' => ['nullable', 'integer', 'min:1'],
            'meter_hour_target' => ['nullable', 'numeric'],
            'next_due_date' => ['nullable', 'date'],
            'checklist_json' => ['nullable', 'array'],
        ]);

        $schedule = MaintenanceSchedule::create($data);

        return ApiResponse::success($schedule, status: 201);
    }

    public function updateSchedule(Request $request, MaintenanceSchedule $schedule): JsonResponse
    {
        $data = $request->validate([
            'name' => ['string', 'max:100'],
            'frequency' => ['in:daily,weekly,monthly,quarterly,semiannual,annual,meter_hours'],
            'interval_value' => ['nullable', 'integer', 'min:1'],
            'meter_hour_target' => ['nullable', 'numeric'],
            'next_due_date' => ['nullable', 'date'],
            'is_active' => ['boolean'],
            'checklist_json' => ['nullable', 'array'],
        ]);

        $schedule->update($data);

        return ApiResponse::success($schedule);
    }

    public function records(Request $request): JsonResponse
    {
        $query = MaintenanceRecord::with(['schedule:id,name,code'])
            ->when($request->input('schedule_id'), fn ($q, $v) => $q->where('schedule_id', $v))
            ->when($request->input('outcome'), fn ($q, $v) => $q->where('outcome', $v))
            ->orderByDesc('execution_date');

        $paginator = $query->paginate($request->input('per_page', 25));

        return ApiResponse::paginated($paginator);
    }

    public function createRecord(Request $request): JsonResponse
    {
        $data = $request->validate([
            'schedule_id' => ['required', 'integer', 'exists:maintenance_schedules,id'],
            'execution_date' => ['required', 'date'],
            'technician_id' => ['nullable', 'integer'],
            'findings' => ['nullable', 'string'],
            'cost_labor' => ['nullable', 'numeric', 'min:0'],
            'cost_material' => ['nullable', 'numeric', 'min:0'],
            'outcome' => ['in:completed,deferred,escalated'],
            'recommendations' => ['nullable', 'string'],
        ]);

        $record = MaintenanceRecord::create($data);

        $schedule = MaintenanceSchedule::find($data['schedule_id']);
        $schedule->update(['last_completed_date' => $data['execution_date']]);

        if ($schedule->frequency === 'daily') {
            $schedule->update(['next_due_date' => now()->addDay()]);
        } elseif ($schedule->frequency === 'weekly') {
            $schedule->update(['next_due_date' => now()->addWeek()]);
        } elseif ($schedule->frequency === 'monthly') {
            $schedule->update(['next_due_date' => now()->addMonth()]);
        } elseif ($schedule->frequency === 'quarterly') {
            $schedule->update(['next_due_date' => now()->addMonths(3)]);
        } elseif ($schedule->frequency === 'semiannual') {
            $schedule->update(['next_due_date' => now()->addMonths(6)]);
        } elseif ($schedule->frequency === 'annual') {
            $schedule->update(['next_due_date' => now()->addYear()]);
        }

        return ApiResponse::success($record, status: 201);
    }
}
