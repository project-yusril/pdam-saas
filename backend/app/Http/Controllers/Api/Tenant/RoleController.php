<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\ApiResponse;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * RoleController — kelola role & izin dalam tenant (PRD 16.2).
 * Semua query otomatis ter-scope ke tenant aktif via BelongsToTenant.
 */
class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions:id,code')->orderBy('name')->get();

        return ApiResponse::success($roles);
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = TenantContext::id();

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('roles', 'code')->where('pdam_org_id', $orgId),
            ],
            'name' => ['required', 'string', 'max:100'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,code'],
        ]);

        $role = Role::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'is_system_default' => false,
        ]);

        $this->syncPermissions($role, $data['permissions'] ?? []);

        return ApiResponse::message('Role dibuat.', $role->load('permissions:id,code'), 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,code'],
        ]);

        if (isset($data['name'])) {
            $role->update(['name' => $data['name']]);
        }

        if ($request->has('permissions')) {
            $this->syncPermissions($role, $data['permissions'] ?? []);
        }

        return ApiResponse::message('Role diperbarui.', $role->load('permissions:id,code'));
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_system_default) {
            return ApiResponse::error('ROLE_PROTECTED', 'Role bawaan tidak dapat dihapus.', null, 422);
        }

        if ($role->users()->exists()) {
            return ApiResponse::error('ROLE_IN_USE', 'Role masih dipakai oleh user. Lepaskan dulu sebelum menghapus.', null, 422);
        }

        $role->permissions()->detach();
        $role->delete();

        return ApiResponse::message('Role dihapus.');
    }

    /** Sinkron permission role berdasarkan daftar kode. */
    protected function syncPermissions(Role $role, array $codes): void
    {
        $ids = Permission::whereIn('code', $codes)->pluck('id')->all();
        $role->permissions()->sync($ids);
    }
}
