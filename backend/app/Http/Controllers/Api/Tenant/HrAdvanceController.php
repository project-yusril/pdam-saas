<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\EmployeeContract;
use App\Models\EmploymentTermination;
use App\Models\HrEmployee;
use App\Services\JournalService;
use App\Services\NotificationChannelService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrAdvanceController extends Controller
{
    public function __construct(private NotificationChannelService $notif) {}

    public function contracts(Request $request): JsonResponse
    {
        $query = EmployeeContract::with('employee:id,name,nip')
            ->when($request->input('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->orderByDesc('end_date');

        return ApiResponse::paginated($query->paginate($request->input('per_page', 25)));
    }

    public function createContract(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hr_employees,id'],
            'contract_number' => ['required', 'string', 'max:30', 'unique:employee_contracts,contract_number'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'type' => ['required', 'in:pkwt,pkwtt'],
        ]);

        $contract = EmployeeContract::create([
            'pdam_org_id' => $request->user()->pdam_org_id,
            ...$data,
            'status' => 'active',
        ]);

        return ApiResponse::success($contract, status: 201);
    }

    public function terminatingSoon(Request $request): JsonResponse
    {
        $contracts = EmployeeContract::with('employee:id,name,nip,position_id,unit_id')
            ->where('status', 'active')
            ->where('end_date', '<=', now()->addDays(30))
            ->where('end_date', '>=', now())
            ->orderBy('end_date')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'contract_number' => $c->contract_number,
                'employee_name' => $c->employee->name,
                'type' => $c->type,
                'end_date' => $c->end_date->toDateString(),
                'days_left' => now()->diffInDays($c->end_date),
            ]);

        return ApiResponse::success($contracts);
    }

    public function terminations(Request $request): JsonResponse
    {
        $query = EmploymentTermination::with('employee:id,name,nip')
            ->when($request->input('reason_type'), fn ($q, $v) => $q->where('reason_type', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('termination_date');

        return ApiResponse::paginated($query->paginate($request->input('per_page', 25)));
    }

    public function createTermination(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hr_employees,id'],
            'termination_date' => ['required', 'date'],
            'reason_type' => ['required', 'in:pensiun,resign,phk,habis_kontrak,meninggal'],
            'reason' => ['nullable', 'string'],
            'severance_amount' => ['nullable', 'numeric', 'min:0'],
            'other_compensation' => ['nullable', 'numeric', 'min:0'],
        ]);

        $employee = HrEmployee::findOrFail($data['employee_id']);

        $termination = EmploymentTermination::create([
            'pdam_org_id' => $request->user()->pdam_org_id,
            'employee_id' => $data['employee_id'],
            'termination_date' => $data['termination_date'],
            'reason_type' => $data['reason_type'],
            'reason' => $data['reason'] ?? null,
            'severance_amount' => $data['severance_amount'] ?? $this->calculateSeverance($employee),
            'other_compensation' => $data['other_compensation'] ?? 0,
            'status' => 'pending',
        ]);

        $employee->update(['status' => 'terminated', 'resign_date' => $data['termination_date']]);

        return ApiResponse::success($termination, status: 201);
    }

    public function approveTermination(Request $request, EmploymentTermination $termination): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected']]);

        $termination->update(['approved_by' => $request->user()->id, 'status' => $data['status']]);

        if ($data['status'] === 'approved' && $termination->severance_amount > 0) {
            app(JournalService::class)->record(
                "Pesangon - {$termination->employee->name}",
                [
                    ['account_code' => '5-103', 'type' => 'DEBIT', 'amount' => $termination->severance_amount + $termination->other_compensation, 'memo' => 'Beban pesangon'],
                    ['account_code' => '2-005', 'type' => 'KREDIT', 'amount' => $termination->severance_amount + $termination->other_compensation, 'memo' => 'Utang pesangon'],
                ],
                'termination',
                $termination->id,
            );
        }

        return ApiResponse::success($termination);
    }

    private function calculateSeverance(HrEmployee $employee): float
    {
        $yearsOfWork = now()->diffInYears($employee->join_date);
        $grade = $employee->grade;
        $baseSalary = $grade ? ($grade->min_salary + $grade->max_salary) / 2 : 0;

        // UU Cipta Kerja - simplified formula
        if ($yearsOfWork < 1) {
            return $baseSalary;
        }
        if ($yearsOfWork < 2) {
            return $baseSalary * 2;
        }
        if ($yearsOfWork < 3) {
            return $baseSalary * 3;
        }
        if ($yearsOfWork < 4) {
            return $baseSalary * 4;
        }
        if ($yearsOfWork < 5) {
            return $baseSalary * 5;
        }
        if ($yearsOfWork < 6) {
            return $baseSalary * 6;
        }
        if ($yearsOfWork < 7) {
            return $baseSalary * 7;
        }
        if ($yearsOfWork < 8) {
            return $baseSalary * 8;
        }

        return $baseSalary * 9;
    }
}
