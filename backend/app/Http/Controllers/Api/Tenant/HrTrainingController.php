<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\PerformanceAppraisal;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Services\NotificationChannelService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrTrainingController extends Controller
{
    public function __construct(private NotificationChannelService $notif) {}

    public function trainings(Request $request): JsonResponse
    {
        $query = Training::when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('start_date');

        return ApiResponse::paginated($query->paginate($request->input('per_page', 25)));
    }

    public function createTraining(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'provider' => ['nullable', 'string', 'max:100'],
            'cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        return ApiResponse::success(Training::create($data), status: 201);
    }

    public function addParticipant(Request $request, Training $training): JsonResponse
    {
        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'max:50'],
            'employee_ids.*' => ['integer', 'exists:hr_employees,id'],
        ]);
        $count = 0;
        foreach ($data['employee_ids'] as $empId) {
            TrainingParticipant::firstOrCreate(
                ['training_id' => $training->id, 'employee_id' => $empId],
                ['attendance_status' => 'registered']
            );
            $count++;
        }

        return ApiResponse::success(['added' => $count]);
    }

    public function updateParticipant(Request $request, Training $training, TrainingParticipant $participant): JsonResponse
    {
        $data = $request->validate(['attendance_status' => ['required', 'in:registered,attended,absent,completed']]);
        $participant->update($data);

        return ApiResponse::success($participant);
    }

    public function certifications(Request $request): JsonResponse
    {
        $query = Certification::with('employee:id,name,nip')
            ->when($request->input('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->orderByDesc('issue_date');

        return ApiResponse::paginated($query->paginate($request->input('per_page', 25)));
    }

    public function createCertification(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hr_employees,id'],
            'name' => ['required', 'string', 'max:200'],
            'issuing_body' => ['nullable', 'string', 'max:100'],
            'certificate_number' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:issue_date'],
        ]);

        return ApiResponse::success(Certification::create($data), status: 201);
    }

    public function expiringCertifications(Request $request): JsonResponse
    {
        $expiring = Certification::with('employee:id,name,nip')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays(30))
            ->where('expiry_date', '>=', now())
            ->orderBy('expiry_date')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'employee' => $c->employee->name,
                'name' => $c->name,
                'expiry_date' => $c->expiry_date->toDateString(),
                'days_left' => now()->diffInDays($c->expiry_date),
            ]);

        return ApiResponse::success($expiring);
    }

    public function appraisals(Request $request): JsonResponse
    {
        $query = PerformanceAppraisal::with('employee:id,name,nip')
            ->when($request->input('period'), fn ($q, $v) => $q->where('period', $v))
            ->when($request->input('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->orderByDesc('period');

        return ApiResponse::paginated($query->paginate($request->input('per_page', 25)));
    }

    public function createAppraisal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hr_employees,id'],
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'score' => ['nullable', 'numeric', 'between:0,100'],
            'kpi_data' => ['nullable', 'array'],
            'comments' => ['nullable', 'string'],
        ]);
        $data['evaluator_id'] = $request->user()->id;
        $data['status'] = 'draft';

        return ApiResponse::success(PerformanceAppraisal::create($data), status: 201);
    }

    public function approveAppraisal(Request $request, PerformanceAppraisal $appraisal): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected'], 'comments' => ['nullable', 'string']]);
        $appraisal->update($data);

        return ApiResponse::success($appraisal);
    }
}
