<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\PdamOrganization;
use App\Models\User;
use App\Services\TenantProvisioningService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * TenantController (Platform) — provisioning & daftar tenant (PRD 16.1).
 * Hanya untuk Super-Admin (guard platform).
 */
class TenantController extends Controller
{
    public function __construct(private TenantProvisioningService $provisioning) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(
            PdamOrganization::query()
                ->withCount('users')
                ->latest()
                ->get()
        );
    }

    public function show(PdamOrganization $tenant): JsonResponse
    {
        $tenant->loadCount('users');
        $tenant->load(['subscriptionModules:id,pdam_org_id,module_code,status,expires_at']);

        return ApiResponse::success($tenant);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9\-]+$/', Rule::unique('pdam_organizations', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'admin_name' => ['required', 'string', 'max:150'],
            'admin_email' => ['required', 'email', 'max:150'],
            'admin_password' => ['required', 'string', 'min:8'],
            'admin_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $org = $this->provisioning->provision(
            orgData: [
                'code' => $data['code'],
                'name' => $data['name'],
                'city' => $data['city'] ?? null,
                'province' => $data['province'] ?? null,
            ],
            adminData: [
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
                'phone' => $data['admin_phone'] ?? null,
            ],
        );

        return ApiResponse::message('PDAM berhasil di-provisioning.', $org, 201);
    }

    /**
     * Aktif/nonaktifkan tenant. Saat nonaktif (suspended) seluruh user tenant
     * tidak dapat login dan token aktif dicabut. PRD 16.1.
     */
    public function toggleStatus(Request $request, PdamOrganization $tenant): JsonResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $tenant->subscription_status = $data['is_active'] ? 'active' : 'suspended';
        $tenant->save();

        // Saat dinonaktifkan, cabut semua token user tenant → sesi aktif langsung putus.
        if (! $data['is_active']) {
            $userIds = $tenant->users()->pluck('id');
            PersonalAccessToken::where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $userIds)
                ->delete();
        }

        $tenant->loadCount('users');

        return ApiResponse::message(
            $data['is_active'] ? 'PDAM diaktifkan.' : 'PDAM dinonaktifkan.',
            $tenant
        );
    }
}
