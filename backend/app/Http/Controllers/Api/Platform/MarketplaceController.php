<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleBundle;
use App\Models\ModuleBundleItem;
use App\Models\ModulePriceTier;
use App\Models\PdamOrganization;
use App\Models\PriceChangeLog;
use App\Models\Promo;
use App\Models\PromoRedemption;
use App\Models\PromoTarget;
use App\Models\SaasInvoice;
use App\Models\Subscription;
use App\Models\SubscriptionModule;
use App\Services\MarketplaceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MarketplaceController extends Controller
{
    public function __construct(private MarketplaceService $marketplace) {}

    public function catalog(): JsonResponse
    {
        $modules = Module::query()
            ->where('is_active', true)
            ->with('priceTiers')
            ->orderBy('tier')
            ->orderBy('code')
            ->get();

        $bundles = ModuleBundle::with('items.module')->orderBy('name')->get();

        return ApiResponse::success([
            'modules' => $modules,
            'bundles' => $bundles,
        ]);
    }

    public function purchase(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:pdam_organizations,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', Rule::in(['module', 'bundle'])],
            'items.*.code' => ['required', 'string'],
            'promo_code' => ['nullable', 'string'],
        ]);

        $org = PdamOrganization::findOrFail($data['organization_id']);

        $result = DB::transaction(function () use ($data, $org, $request) {
            $modules = $this->resolveModules($data['items']);
            if ($modules instanceof JsonResponse) {
                return $modules;
            }

            $dependencyError = $this->validateDependencies($org, $modules);
            if ($dependencyError !== null) {
                return $dependencyError;
            }

            $customerCount = DB::table('customers')->where('pdam_org_id', $org->id)->count();
            $lines = $this->resolvePriceLines($org->id, $data['items'], $customerCount);
            if ($lines instanceof JsonResponse) {
                return $lines;
            }
            $totalPrice = (float) $lines->sum('price');

            $promo = $this->resolvePromo($data['promo_code'] ?? null, $org->id);
            $eligibleLines = $promo ? $lines->filter(fn ($line) => $this->promoApplies($promo, $line['module'])) : collect();
            $eligibleTotal = (float) $eligibleLines->sum('price');
            $discount = $promo ? $this->calculateDiscount($promo, $eligibleTotal) : 0.0;
            $finalPrice = max(0, $totalPrice - $discount);

            foreach ($lines as $line) {
                $module = $line['module'];
                SubscriptionModule::query()->updateOrCreate(
                    ['pdam_org_id' => $org->id, 'module_code' => $module->code],
                    [
                        'status' => 'active',
                        'activation_method' => $promo ? 'promo' : 'manual_transfer',
                        'amount_paid' => $line['price'],
                        'activated_by' => $request->user()?->getKey(),
                        'activated_at' => now(),
                        'expires_at' => now()->addYear(),
                        'locked_by' => null,
                        'locked_at' => null,
                    ],
                );
            }

            $subscription = Subscription::query()->updateOrCreate(
                ['pdam_org_id' => $org->id, 'status' => 'active'],
                [
                    'plan_tier' => 'modular',
                    'start_date' => now()->toDateString(),
                    'end_date' => now()->addYear()->toDateString(),
                    'billing_cycle' => 'yearly',
                ],
            );

            SaasInvoice::query()->withoutGlobalScopes()->create([
                'pdam_org_id' => $org->id,
                'period' => now()->format('Y'),
                'amount' => $finalPrice,
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            if ($promo !== null) {
                $this->recordPromo($promo, $org->id, $eligibleLines, $discount, $eligibleTotal);
            }

            return [
                'subscription' => $subscription,
                'modules' => $modules->pluck('code')->values(),
                'original_price' => $totalPrice,
                'discount' => $discount,
                'final_price' => $finalPrice,
            ];
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return ApiResponse::success($result, status: 201);
    }

    public function promos(Request $request): JsonResponse
    {
        $query = Promo::query()->when(
            $request->filled('status'),
            fn ($builder) => $builder->where('status', $request->string('status')),
        );

        return ApiResponse::paginated($query->latest()->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function createPromo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:promos,code'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'scope_type' => ['required', Rule::in(['all', 'tier', 'modules', 'tenant'])],
            'scope_value' => ['nullable', 'array'],
            'target_type' => ['required', Rule::in(['all_tenants', 'specific'])],
            'target_tenant_ids' => ['nullable', 'array'],
            'target_tenant_ids.*' => ['integer', 'exists:pdam_organizations,id'],
            'max_discount_cap' => ['nullable', 'numeric', 'min:0'],
            'usage_quota' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['required', Rule::in(['scheduled', 'active', 'disabled'])],
        ]);

        if ($data['discount_type'] === 'percent' && $data['discount_value'] > 100) {
            return ApiResponse::error('INVALID_DISCOUNT', 'Diskon persen tidak boleh lebih dari 100.', null, 422);
        }
        if ($data['target_type'] === 'specific' && empty($data['target_tenant_ids'])) {
            return ApiResponse::error('TARGET_REQUIRED', 'Tenant target wajib dipilih.', null, 422);
        }

        $promo = DB::transaction(function () use ($data, $request) {
            $promo = Promo::create([
                ...collect($data)->except('target_tenant_ids')->all(),
                'used_count' => 0,
                'created_by' => $request->user()?->getKey(),
            ]);

            foreach ($data['target_tenant_ids'] ?? [] as $tenantId) {
                PromoTarget::create(['promo_id' => $promo->id, 'pdam_org_id' => $tenantId]);
            }

            return $promo->load('targets');
        });

        return ApiResponse::success($promo, status: 201);
    }

    public function superAdminDashboard(): JsonResponse
    {
        $activeTenants = PdamOrganization::where('subscription_status', 'active')->count();
        $annualRevenue = (float) SaasInvoice::query()->withoutGlobalScopes()->where('status', 'paid')->sum('amount');
        $expiring = SubscriptionModule::query()
            ->where('status', 'active')
            ->whereBetween('expires_at', [now(), now()->addDays(30)])
            ->distinct('pdam_org_id')
            ->count('pdam_org_id');
        $popularModules = SubscriptionModule::query()
            ->where('status', 'active')
            ->selectRaw('module_code, COUNT(*) as count')
            ->groupBy('module_code')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return ApiResponse::success([
            'active_tenants' => $activeTenants,
            'mrr' => round($annualRevenue / 12, 2),
            'arr' => round($annualRevenue, 2),
            'expiring_soon' => $expiring,
            'popular_modules' => $popularModules,
        ]);
    }

    public function managePrice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'module_code' => ['required', 'string', 'exists:modules,code'],
            'min_customers' => ['required', 'integer', 'min:0'],
            'max_customers' => ['nullable', 'integer', 'gte:min_customers'],
            'price_year' => ['required', 'numeric', 'min:0'],
        ]);

        $tier = ModulePriceTier::firstOrNew([
            'module_code' => $data['module_code'],
            'min_customers' => $data['min_customers'],
            'max_customers' => $data['max_customers'] ?? null,
        ]);
        $oldPrice = $tier->exists ? (float) $tier->price_year : null;
        $tier->price_year = $data['price_year'];
        $tier->save();

        PriceChangeLog::create([
            'module_code' => $data['module_code'],
            'old_price' => $oldPrice,
            'new_price' => $data['price_year'],
            'changed_by' => $request->user()?->getKey(),
            'changed_at' => now(),
        ]);

        return ApiResponse::success($tier);
    }

    public function bundles(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            ModuleBundle::with('items.module')->paginate(min($request->integer('per_page', 25), 100)),
        );
    }

    public function createBundle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:module_bundles,code'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'price_year' => ['required', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.module_code' => ['required', 'string', 'distinct', 'exists:modules,code'],
        ]);

        $bundle = DB::transaction(function () use ($data) {
            $bundle = ModuleBundle::create(collect($data)->except('items')->all());
            $moduleIds = Module::whereIn('code', collect($data['items'])->pluck('module_code'))
                ->pluck('id', 'code');

            foreach ($data['items'] as $item) {
                ModuleBundleItem::create([
                    'module_bundle_id' => $bundle->id,
                    'module_id' => $moduleIds[$item['module_code']],
                ]);
            }

            return $bundle->load('items.module');
        });

        return ApiResponse::success($bundle, status: 201);
    }

    private function resolveModules(array $items)
    {
        $modules = collect();

        foreach ($items as $item) {
            if ($item['type'] === 'module') {
                $module = Module::where('code', $item['code'])->where('is_active', true)->first();
                if ($module === null) {
                    return ApiResponse::error('MODULE_NOT_FOUND', "Modul {$item['code']} tidak ditemukan.", null, 422);
                }
                $modules->push($module);

                continue;
            }

            $bundle = ModuleBundle::with('items.module')->where('code', $item['code'])->first();
            if ($bundle === null) {
                return ApiResponse::error('BUNDLE_NOT_FOUND', "Bundle {$item['code']} tidak ditemukan.", null, 422);
            }
            $modules = $modules->concat($bundle->items->pluck('module'));
        }

        return $modules->filter()->unique('code')->values();
    }

    private function validateDependencies(PdamOrganization $org, $modules): ?JsonResponse
    {
        $purchasedCodes = $modules->pluck('code');
        $activeCodes = SubscriptionModule::query()
            ->where('pdam_org_id', $org->id)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('module_code');
        $availableCodes = $purchasedCodes->merge($activeCodes)->unique();

        foreach ($modules as $module) {
            $missing = collect($module->dependencies ?? [])->diff($availableCodes);
            if ($missing->isNotEmpty()) {
                return ApiResponse::error(
                    'DEPENDENCY_MISSING',
                    "Modul {$module->code} membutuhkan modul: {$missing->implode(', ')}.",
                    ['missing_modules' => $missing->values()],
                    422,
                );
            }
        }

        return null;
    }

    private function resolvePrice(int $orgId, Module $module, int $customerCount): float
    {
        return $this->marketplace->resolvePrice($orgId, $module, $customerCount);
    }

    private function resolvePriceLines(int $orgId, array $items, int $customerCount)
    {
        $lines = collect();
        $seen = collect();

        foreach ($items as $item) {
            if ($item['type'] === 'module') {
                $module = Module::where('code', $item['code'])->where('is_active', true)->first();
                if ($module === null) {
                    return ApiResponse::error('MODULE_NOT_FOUND', "Modul {$item['code']} tidak ditemukan.", null, 422);
                }
                if ($seen->contains($module->code)) {
                    return ApiResponse::error('DUPLICATE_ITEM', "Modul {$module->code} dipilih lebih dari sekali.", null, 422);
                }
                $seen->push($module->code);
                $lines->push([
                    'module' => $module,
                    'price' => $this->resolvePrice($orgId, $module, $customerCount),
                ]);

                continue;
            }

            $bundle = ModuleBundle::with('items.module')->where('code', $item['code'])->first();
            if ($bundle === null) {
                return ApiResponse::error('BUNDLE_NOT_FOUND', "Bundle {$item['code']} tidak ditemukan.", null, 422);
            }
            $bundleModules = $bundle->items->pluck('module')->filter();
            $duplicate = $bundleModules->pluck('code')->first(fn ($code) => $seen->contains($code));
            if ($duplicate !== null) {
                return ApiResponse::error('DUPLICATE_ITEM', "Modul {$duplicate} dipilih lebih dari sekali.", null, 422);
            }

            $unitPrices = $bundleModules->mapWithKeys(fn ($module) => [
                $module->code => $this->resolvePrice($orgId, $module, $customerCount),
            ]);
            $unitTotal = (float) $unitPrices->sum();
            $bundlePrice = (float) $bundle->price_year;

            foreach ($bundleModules as $module) {
                $seen->push($module->code);
                $share = $unitTotal > 0
                    ? $bundlePrice * ((float) $unitPrices[$module->code] / $unitTotal)
                    : $bundlePrice / max(1, $bundleModules->count());
                $lines->push(['module' => $module, 'price' => round($share, 2)]);
            }
        }

        return $lines;
    }

    private function resolvePromo(?string $code, int $orgId): ?Promo
    {
        if ($code === null || $code === '') {
            return null;
        }

        return Promo::query()
            ->where('code', $code)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->where(fn ($query) => $query->whereNull('usage_quota')->orWhereColumn('used_count', '<', 'usage_quota'))
            ->where(function ($query) use ($orgId) {
                $query->where('target_type', 'all_tenants')
                    ->orWhere(fn ($target) => $target
                        ->where('target_type', 'specific')
                        ->whereHas('targets', fn ($targets) => $targets->where('pdam_org_id', $orgId)));
            })
            ->lockForUpdate()
            ->first();
    }

    private function calculateDiscount(Promo $promo, float $total): float
    {
        $discount = $promo->discount_type === 'percent'
            ? $total * ((float) $promo->discount_value / 100)
            : (float) $promo->discount_value;

        if ($promo->max_discount_cap !== null) {
            $discount = min($discount, (float) $promo->max_discount_cap);
        }

        return round(min($discount, $total), 2);
    }

    private function promoApplies(Promo $promo, Module $module): bool
    {
        $scope = $promo->scope_value ?? [];

        return match ($promo->scope_type) {
            'all', 'tenant' => true,
            'tier' => (int) ($scope['tier'] ?? 0) === (int) $module->tier,
            'modules' => in_array($module->code, $scope['modules'] ?? [], true),
            default => false,
        };
    }

    private function recordPromo(Promo $promo, int $orgId, $lines, float $discount, float $total): void
    {
        foreach ($lines as $line) {
            $share = $total > 0 ? $discount * ($line['price'] / $total) : 0;
            PromoRedemption::create([
                'promo_id' => $promo->id,
                'pdam_org_id' => $orgId,
                'module_code' => $line['module']->code,
                'original_price' => $line['price'],
                'discount_amount' => round($share, 2),
                'final_price' => max(0, round($line['price'] - $share, 2)),
                'redeemed_at' => now(),
            ]);
        }

        $promo->increment('used_count');
    }
}
