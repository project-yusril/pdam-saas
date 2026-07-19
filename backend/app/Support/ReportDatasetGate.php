<?php

namespace App\Support;

use App\Models\SubscriptionModule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReportDatasetGate
{
    public static function authorize(Request $request, array $dataset, bool $checkPermission): ?JsonResponse
    {
        return self::authorizeUser($request->user(), $dataset, $checkPermission);
    }

    public static function authorizeUser(User $user, array $dataset, bool $checkPermission): ?JsonResponse
    {
        $moduleCode = $dataset['module'];

        if ($moduleCode !== null && ! self::hasActiveModule($user->pdam_org_id, $moduleCode)) {
            return response()->json([
                'message' => "Modul {$moduleCode} tidak aktif untuk PDAM ini.",
                'module' => $moduleCode,
                'error_code' => 'MODULE_LOCKED',
            ], 403);
        }

        $permission = $dataset['permission'];
        if ($checkPermission && ! $user->is_tenant_admin && ! $user->hasPermission($permission)) {
            return response()->json([
                'message' => 'Anda tidak memiliki izin untuk dataset ini.',
                'permission' => $permission,
                'error_code' => 'PERMISSION_DENIED',
            ], 403);
        }

        return null;
    }

    public static function hasActiveModule(int $orgId, string $moduleCode): bool
    {
        $entitlement = SubscriptionModule::query()
            ->withoutGlobalScopes()
            ->where('pdam_org_id', $orgId)
            ->where('module_code', $moduleCode)
            ->first();

        return $entitlement?->isActiveNow() ?? false;
    }
}
