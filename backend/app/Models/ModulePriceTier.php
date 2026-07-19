<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModulePriceTier extends Model
{
    protected $fillable = ['module_code', 'min_customers', 'max_customers', 'price_year'];

    protected function casts(): array
    {
        return ['price_year' => 'decimal:2'];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_code', 'code');
    }
}
