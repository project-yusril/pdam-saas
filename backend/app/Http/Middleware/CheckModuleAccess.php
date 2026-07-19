<?php

namespace App\Http\Middleware;

use App\Models\SubscriptionModule;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckModuleAccess — gerbang entitlement modul (PRD 4.D.2 & 5).
 * Pakai: ->middleware('module:WH'). Tolak 403 jika tenant tidak punya modul aktif.
 * Modul default (is_default, mis. CORE) selalu lolos.
 */
class CheckModuleAccess
{
    public function handle(Request $request, Closure $next, string $moduleCode): Response
    {
        $orgId = TenantContext::id();

        if ($orgId === null) {
            return response()->json([
                'message' => 'Konteks tenant tidak ditemukan.',
            ], 403);
        }

        $sub = SubscriptionModule::query()
            ->withoutGlobalScopes()
            ->where('pdam_org_id', $orgId)
            ->where('module_code', $moduleCode)
            ->first();

        if (! $sub || ! $sub->isActiveNow()) {
            return response()->json([
                'message' => "Modul {$moduleCode} tidak aktif untuk PDAM ini.",
                'module' => $moduleCode,
                'error_code' => 'MODULE_LOCKED',
            ], 403);
        }

        return $next($request);
    }
}
