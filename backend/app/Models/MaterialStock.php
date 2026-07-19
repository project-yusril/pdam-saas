<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MaterialStock — stok material per gudang. Fase 4.1 */
class MaterialStock extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'material_id', 'warehouse_id', 'current_stock', 'minimum_stock',
    ];

    protected function casts(): array
    {
        return ['current_stock' => 'decimal:2', 'minimum_stock' => 'decimal:2'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function isBelowMinimum(): bool
    {
        return $this->current_stock < $this->minimum_stock;
    }
}
