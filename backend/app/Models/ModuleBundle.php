<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModuleBundle extends Model
{
    protected $fillable = ['code', 'name', 'price_year', 'description'];

    protected function casts(): array
    {
        return ['price_year' => 'decimal:2'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ModuleBundleItem::class, 'module_bundle_id');
    }
}
