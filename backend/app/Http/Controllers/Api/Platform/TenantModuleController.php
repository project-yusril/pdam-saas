<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\PdamOrganization;
use App\Models\Subscription;
use App\Models\SubscriptionModule;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TenantModuleController — marketplace: kelola entitlement modul per tenant (PRD 4.E).
 * Super-Admin bisa activate/lock modul untuk PDAM tertentu.
 */
class TenantModuleController extends Controller
{
    public function index(PdamOrganization $tenant): JsonResponse
    {
        $entitlements = SubscriptionModule::withoutGlobalScopes()
            ->where('pdam_org_id', $tenant->id)
            ->get();

        return ApiResponse::success($entitlements);
    }

    public function activate(Request $request, PdamOrganization $tenant, string $moduleCode): JsonResponse
    {
        $data = $request->validate([
            'expires_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $module = Module::where('code', $moduleCode)->where('is_active', true)->first();
        if (! $module) {
            return ApiResponse::error('MODULE_NOT_FOUND', 'Modul tidak ditemukan.', null, 404);
        }

        $entitlement = SubscriptionModule::withoutGlobalScopes()
            ->where('pdam_org_id', $tenant->id)
            ->where('module_code', $moduleCode)
            ->first();

        if (! $entitlement) {
            return ApiResponse::error('ENTITLEMENT_NOT_FOUND', 'Entitlement modul untuk tenant ini tidak ada.', null, 404);
        }

        $trialDays = (int) config('business.billing.trial_days', 0);
        $expires = $data['expires_at'] ?? null;
        $method = 'manual_superadmin';
        if ($expires === null && $trialDays > 0 && (int) $module->tier > 0) {
            // PRD §23: lama trial ditetapkan manajemen (env PDAM_TRIAL_DAYS via config business).
            $expires = now()->addDays($trialDays)->toDateString();
            $method = 'trial';
            Subscription::withoutGlobalScopes()->updateOrCreate(
                ['pdam_org_id' => $tenant->id, 'status' => 'active'],
                ['plan_tier' => 'trial', 'start_date' => now()->toDateString(), 'end_date' => $expires, 'billing_cycle' => 'trial'],
            );
        }

        $entitlement->update([
            'status' => 'active',
            'activation_method' => $method,
            'activated_by' => $request->user()?->getKey(),
            'activated_at' => now(),
            'expires_at' => $expires,
            'locked_by' => null,
            'locked_at' => null,
            'note' => $data['note'] ?? null,
        ]);

        return ApiResponse::message("Modul {$moduleCode} diaktifkan untuk {$tenant->name}.", $entitlement);
    }

    public function lock(Request $request, PdamOrganization $tenant, string $moduleCode): JsonResponse
    {
        $module = Module::where('code', $moduleCode)->first();
        if ($module && $module->is_default) {
            return ApiResponse::error('MODULE_LOCKED_FORBIDDEN', 'Modul CORE/default tidak dapat dikunci.', null, 422);
        }

        $entitlement = SubscriptionModule::withoutGlobalScopes()
            ->where('pdam_org_id', $tenant->id)
            ->where('module_code', $moduleCode)
            ->first();

        if (! $entitlement) {
            return ApiResponse::error('ENTITLEMENT_NOT_FOUND', 'Entitlement modul untuk tenant ini tidak ada.', null, 404);
        }

        $entitlement->update([
            'status' => 'locked',
            'locked_by' => $request->user()?->getKey(),
            'locked_at' => now(),
        ]);

        return ApiResponse::message("Modul {$moduleCode} dikunci untuk {$tenant->name}.", $entitlement);
    }
}
