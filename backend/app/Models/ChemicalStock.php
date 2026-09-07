<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** ChemicalStock — Auto-generated dari skema tabel. */
class ChemicalStock extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'chemical_id', 'warehouse_id', 'batch_number', 'expiry_date', 'quantity', 'unit', 'unit_cost'];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'datetime',
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function chemical(): BelongsTo
    {
        return $this->belongsTo(Chemical::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ChemicalTransaction::class);
    }
}
