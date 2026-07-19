<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SetTenant — set pdam_org_id aktif dari user terautentikasi (PRD 5).
 * Dipasang setelah auth:sanctum. PlatformAdmin tidak punya pdam_org_id (lintas tenant).
 */
class SetTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && isset($user->pdam_org_id) && $user->pdam_org_id !== null) {
            TenantContext::set((int) $user->pdam_org_id);
        }

        try {
            return $next($request);
        } finally {
            TenantContext::clear();
        }
    }
}
