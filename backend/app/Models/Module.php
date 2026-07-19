<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Module — katalog modul platform. PRD 4.C.
 */
class Module extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'tier',
        'base_price_year',
        'dependencies',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'dependencies' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'base_price_year' => 'decimal:2',
        ];
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(ModulePriceTier::class, 'module_code', 'code');
    }

    public function bundleItems(): HasMany
    {
        return $this->hasMany(ModuleBundleItem::class, 'module_id');
    }
}
