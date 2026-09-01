<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** ChemicalUsage — Auto-generated dari skema tabel. */
class ChemicalUsage extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'chemical_id', 'usage_date', 'quantity', 'unit', 'water_produced_m3', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'usage_date' => 'datetime',
            'quantity' => 'decimal:2',
            'water_produced_m3' => 'decimal:2',
        ];
    }
public function chemical(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Chemical::class); }
}

