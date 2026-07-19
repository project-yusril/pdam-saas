<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\EmployeeContract;
use App\Models\HrEmployee;
use App\Support\ApiResponse;
use App\Support\ListQueryParams;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrEmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $params = ListQueryParams::fromRequest($request, ['nip', 'name']);
        $query = HrEmployee::with(['position', 'unit', 'grade'])
            ->when($request->input('unit_id'), fn ($q, $v) => $q->where('unit_id', $v))
            ->when($request->input('employment_status'), fn ($q, $v) => $q->where('employment_status', $v))
            ->when($request->input('zone_id'), fn ($q, $v) => $q->where('zone_id', $v));
        $params->apply($query, ['nip', 'name']);

        return ApiResponse::paginated($query->paginate($params->perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nip' => ['nullable', 'string', 'max:20'],
            'nik' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', 'in:Laki-laki,Perempuan'],
            'birth_date' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'position_id' => ['nullable', 'integer'],
            'unit_id' => ['nullable', 'integer'],
            'grade_id' => ['nullable', 'integer'],
            'zone_id' => ['nullable', 'integer'],
            'employment_status' => ['in:tetap,kontrak,honorer,direksi,dewan_pengawas'],
            'join_date' => ['required', 'date'],
            'tax_status' => ['nullable', 'string', 'max:5'],
            'dependents' => ['nullable', 'integer', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'bank_account' => ['nullable', 'string', 'max:30'],
            'education' => ['nullable', 'string', 'max:50'],
            'npwp' => ['nullable', 'string', 'max:20'],
            'no_bpjs_kesehatan' => ['nullable', 'string', 'max:20'],
            'no_bpjs_tk' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
        ]);

        $data['status'] = 'active';
        $employee = HrEmployee::create($data);

        return ApiResponse::success($employee, status: 201);
    }

    public function show(HrEmployee $employee): JsonResponse
    {
        $employee->load(['position', 'unit', 'grade', 'zone']);

        return ApiResponse::success($employee);
    }

    public function update(Request $request, HrEmployee $employee): JsonResponse
    {
        $data = $request->validate([
            'name' => ['string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'position_id' => ['nullable', 'integer'],
            'unit_id' => ['nullable', 'integer'],
            'employment_status' => ['in:tetap,kontrak,honorer,direksi,dewan_pengawas'],
            'tax_status' => ['nullable', 'string', 'max:5'],
            'dependents' => ['nullable', 'integer', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'bank_account' => ['nullable', 'string', 'max:30'],
        ]);

        $employee->update($data);

        return ApiResponse::success($employee);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $orgId = $request->user()->pdam_org_id;

        return ApiResponse::success([
            'total_employees' => HrEmployee::where('pdam_org_id', $orgId)->where('status', 'active')->count(),
            'by_status' => HrEmployee::where('pdam_org_id', $orgId)->selectRaw('employment_status, COUNT(*) as count')->groupBy('employment_status')->pluck('count', 'employment_status'),
            'by_unit' => HrEmployee::where('pdam_org_id', $orgId)->with('unit:id,name')->get()->groupBy('unit.name')->map->count(),
            'certifications_expiring' => Certification::where('pdam_org_id', $orgId)->where('expiry_date', '<', now()->addDays(30))->where('expiry_date', '>', now())->count(),
            'contracts_expiring' => EmployeeContract::where('pdam_org_id', $orgId)->where('end_date', '<', now()->addDays(30))->where('end_date', '>', now())->count(),
        ]);
    }
}
