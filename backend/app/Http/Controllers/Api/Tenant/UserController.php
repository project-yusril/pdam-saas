<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use App\Support\ApiResponse;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * UserController — kelola user internal dalam tenant + assign role (PRD 16.2).
 * Query otomatis ter-scope ke tenant aktif via BelongsToTenant.
 */
class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);

        $query = User::with('roles:id,code,name')->orderBy('name');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->integer('zone_id'));
        }

        return ApiResponse::paginated($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = TenantContext::id();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'email', 'max:150',
                Rule::unique('users', 'email')->where('pdam_org_id', $orgId),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'roles' => ['array'],
            'roles.*' => ['string'],
        ]);

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'zone_id' => $data['zone_id'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => true,
            'is_tenant_admin' => false,
        ]);
        $user->save(); // pdam_org_id otomatis dari BelongsToTenant

        $this->assignRoles($user, $data['roles'] ?? []);

        return ApiResponse::message('User dibuat.', $user->load('roles:id,code,name'), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $orgId = TenantContext::id();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => [
                'sometimes', 'email', 'max:150',
                Rule::unique('users', 'email')->where('pdam_org_id', $orgId)->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'password' => ['sometimes', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return ApiResponse::message('User diperbarui.', $user->fresh()->load('roles:id,code,name'));
    }

    public function syncRoles(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['string'],
        ]);

        $this->assignRoles($user, $data['roles']);

        return ApiResponse::message('Role user diperbarui.', $user->load('roles:id,code,name'));
    }

    /** Assign pegawai ke wilayah (zone). */
    public function assignZone(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
        ]);

        $user->update(['zone_id' => $data['zone_id']]);

        return ApiResponse::message('Wilayah pegawai diperbarui.', $user->fresh()->load('zone:id,code,name'));
    }

    /** Assign role berdasarkan kode, hanya role milik tenant aktif. */
    protected function assignRoles(User $user, array $codes): void
    {
        $ids = Role::whereIn('code', $codes)->pluck('id')->all();
        $user->roles()->sync($ids);
    }
}
