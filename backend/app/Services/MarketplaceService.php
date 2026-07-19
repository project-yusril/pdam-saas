<?php

namespace App\Services;

use App\Exceptions\MarketplaceException;
use App\Models\Module;
use App\Models\ModuleBundle;
use App\Models\PdamOrganization;
use App\Models\SubscriptionModule;
use App\Models\TenantModuleOverride;
use Illuminate\Support\Facades\DB;

class MarketplaceService
{
    public function catalog(PdamOrganization $organization): array
    {
        $customerCount = DB::table('customers')->where('pdam_org_id', $organization->id)->count();
        $entitlements = SubscriptionModule::query()->withoutGlobalScopes()
            ->where('pdam_org_id', $organization->id)->get()->keyBy('module_code');
        $activeCodes = $entitlements->filter->isActiveNow()->keys();

        $modules = Module::query()->where('is_active', true)->orderBy('tier')->orderBy('code')->get()
            ->map(function (Module $module) use ($organization, $customerCount, $entitlements, $activeCodes) {
                $missing = collect($module->dependencies ?? [])->diff($activeCodes)->values();
                $entitlement = $entitlements->get($module->code);

                return [
                    'code' => $module->code,
                    'name' => $module->name,
                    'description' => $module->description,
                    'tier' => $module->tier,
                    'dependencies' => $module->dependencies ?? [],
                    'price_year' => $this->resolvePrice($organization->id, $module, $customerCount),
                    'entitled' => $entitlement?->isActiveNow() ?? false,
                    'entitlement_status' => $entitlement?->status ?? 'locked',
                    'expires_at' => $entitlement?->expires_at,
                    'dependency_satisfied' => $missing->isEmpty(),
                    'missing_dependencies' => $missing,
                ];
            });

        return ['customer_count' => $customerCount, 'modules' => $modules];
    }

    public function quote(PdamOrganization $organization, array $items): array
    {
        $customerCount = DB::table('customers')->where('pdam_org_id', $organization->id)->count();
        $lines = collect();
        $seen = collect();

        foreach ($items as $item) {
            if ($item['type'] === 'module') {
                $module = Module::where('code', $item['code'])->where('is_active', true)->first();
                if (! $module) {
                    throw new MarketplaceException('MODULE_NOT_FOUND', "Modul {$item['code']} tidak ditemukan.");
                }
                $this->addLine($lines, $seen, $module, $this->resolvePrice($organization->id, $module, $customerCount));

                continue;
            }

            $bundle = ModuleBundle::with('items.module')->where('code', $item['code'])->first();
            if (! $bundle) {
                throw new MarketplaceException('BUNDLE_NOT_FOUND', "Bundle {$item['code']} tidak ditemukan.");
            }
            $modules = $bundle->items->pluck('module')->filter();
            $prices = $modules->mapWithKeys(fn (Module $module) => [$module->code => $this->resolvePrice($organization->id, $module, $customerCount)]);
            $baseTotal = (float) $prices->sum();
            foreach ($modules as $module) {
                $share = $baseTotal > 0 ? (float) $bundle->price_year * ($prices[$module->code] / $baseTotal) : (float) $bundle->price_year / max(1, $modules->count());
                $this->addLine($lines, $seen, $module, round($share, 2));
            }
        }

        $this->assertDependencies($organization->id, $lines->pluck('module'));

        return ['lines' => $lines, 'total' => round((float) $lines->sum('price'), 2)];
    }

    public function assertDependencies(int $organizationId, $modules): void
    {
        $active = SubscriptionModule::query()->withoutGlobalScopes()->where('pdam_org_id', $organizationId)
            ->where('status', 'active')->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('module_code');
        $available = collect($modules)->pluck('code')->merge($active)->unique();

        foreach ($modules as $module) {
            $missing = collect($module->dependencies ?? [])->diff($available)->values();
            if ($missing->isNotEmpty()) {
                throw new MarketplaceException('DEPENDENCY_MISSING', "Modul {$module->code} membutuhkan modul: {$missing->implode(', ')}.", ['missing_modules' => $missing]);
            }
        }
    }

    public function resolvePrice(int $organizationId, Module $module, int $customerCount): float
    {
        $override = TenantModuleOverride::query()->where('pdam_org_id', $organizationId)->where('module_code', $module->code)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', today()))->latest()->first();
        if ($override) {
            return (float) $override->custom_price;
        }

        $tier = $module->priceTiers()->where('min_customers', '<=', $customerCount)
            ->where(fn ($query) => $query->whereNull('max_customers')->orWhere('max_customers', '>=', $customerCount))
            ->orderByDesc('min_customers')->first();

        return (float) ($tier?->price_year ?? $module->base_price_year);
    }

    private function addLine($lines, $seen, Module $module, float $price): void
    {
        if ($seen->contains($module->code)) {
            throw new MarketplaceException('DUPLICATE_ITEM', "Modul {$module->code} dipilih lebih dari sekali.");
        }
        $seen->push($module->code);
        $lines->push(['module' => $module, 'price' => $price]);
    }
}
