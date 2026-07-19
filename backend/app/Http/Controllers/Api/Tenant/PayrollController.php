<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\HrEmployee;
use App\Services\PayrollService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(private PayrollService $payroll) {}

    public function calculate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hr_employees,id'],
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $employee = HrEmployee::findOrFail($data['employee_id']);
        $result = $this->payroll->calculate($employee, $data['period']);

        return ApiResponse::success($result);
    }

    public function batchRun(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer'],
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'post_journal' => ['boolean'],
        ]);

        $result = $this->payroll->runBatch($data['employee_ids'], $data['period']);

        if ($data['post_journal'] ?? false) {
            $this->payroll->postJournal($result, $data['period']);
        }

        return ApiResponse::success($result);
    }

    public function slip(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hr_employees,id'],
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $employee = HrEmployee::findOrFail($data['employee_id']);
        $calc = $this->payroll->calculate($employee, $data['period']);

        return ApiResponse::success([
            'header' => [
                'organization' => $employee->pdamOrganization?->name ?? 'PDAM',
                'period' => $data['period'],
                'employee_name' => $employee->name,
                'nip' => $employee->nip,
                'position' => $employee->position?->name,
            ],
            'salary_detail' => $calc,
        ]);
    }
}
