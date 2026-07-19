<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** AssetCategory — klasifikasi aset + default masa manfaat/metode + akun COA. Fase 7.1 */
class AssetCategory extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'code', 'name', 'useful_life_months', 'depreciation_method',
        'declining_rate', 'is_depreciable', 'asset_account_code',
        'accumulation_account_code', 'expense_account_code',
    ];

    protected function casts(): array
    {
        return [
            'useful_life_months' => 'integer',
            'declining_rate' => 'decimal:2',
            'is_depreciable' => 'boolean',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(FixedAsset::class);
    }
}
