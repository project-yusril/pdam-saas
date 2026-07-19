<?php

use App\Models\ChartOfAccount;
use App\Models\PdamOrganization;
use App\Models\TariffCategory;
use App\Models\TariffTier;
use App\Services\BillingService;
use App\Support\TenantContext;

$org = PdamOrganization::first();
TenantContext::set($org->id);

$tariffs = TariffCategory::count();
$tiers = TariffTier::count();
$coa = ChartOfAccount::count();

echo "Tenant: {$org->name}\n";
echo "Golongan tarif : {$tariffs} (harusnya 17)\n";
echo "Tier tarif     : {$tiers} (harusnya 51 = 17×3)\n";
echo "Akun COA       : {$coa} (harusnya 12)\n";

$cat = TariffCategory::where('code', '2A3')->first();
$calc = app(BillingService::class)->calculate($cat, 35);
echo "\nUji 2A3 @ 35 m³:\n";
echo "  Biaya air : Rp " . number_format($calc['water_charge'], 0, ',', '.') . " (harusnya 180.000)\n";
echo "  Total     : Rp " . number_format($calc['total'], 0, ',', '.') . " (air + komponen tetap)\n";

$inventory = ChartOfAccount::where('code', '1-003')->first();
echo "\nAkun Persediaan Material: {$inventory->name} [{$inventory->type}/{$inventory->normal_balance}]\n";
echo "  (Perbaikan draft: material = ASET, bukan biaya langsung)\n";

TenantContext::clear();
