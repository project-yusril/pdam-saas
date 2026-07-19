<?php

namespace App\Http\Middleware;

use App\Models\SubscriptionModule;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckPermission — gerbang RBAC per aksi (PRD 4.D.3).
 * Pakai: ->middleware('permission:wh.material.create').
 * Tenant admin melewati pengecekan (akses penuh di tenant-nya).
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permissionCode): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Tidak terautentikasi.'], 401);
        }

        $moduleCode = $this->moduleCode($permissionCode);
        if ($moduleCode !== null && ! $this->hasActiveModule($moduleCode)) {
            return response()->json([
                'message' => "Modul {$moduleCode} tidak aktif untuk PDAM ini.",
                'module' => $moduleCode,
                'error_code' => 'MODULE_LOCKED',
            ], 403);
        }

        // Admin tenant melewati RBAC, tetapi tidak pernah melewati entitlement.
        if (! empty($user->is_tenant_admin)) {
            return $next($request);
        }

        if (! $user->hasPermission($permissionCode)) {
            return response()->json([
                'message' => 'Anda tidak memiliki izin untuk aksi ini.',
                'permission' => $permissionCode,
                'error_code' => 'PERMISSION_DENIED',
            ], 403);
        }

        return $next($request);
    }

    private function moduleCode(string $permissionCode): ?string
    {
        $prefix = strtoupper(strtok($permissionCode, '.'));

        return match ($prefix) {
            'IAM', 'CORE' => null,
            'FIN' => 'FIN+',
            'BILL' => 'BILL+',
            default => $prefix,
        };
    }

    private function hasActiveModule(string $moduleCode): bool
    {
        $orgId = TenantContext::id();
        if ($orgId === null) {
            return false;
        }

        $entitlement = SubscriptionModule::query()
            ->withoutGlobalScopes()
            ->where('pdam_org_id', $orgId)
            ->where('module_code', $moduleCode)
            ->first();

        return $entitlement?->isActiveNow() ?? false;
    }
}
