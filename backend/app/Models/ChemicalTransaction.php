<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** ChemicalTransaction — Auto-generated dari skema tabel. */
class ChemicalTransaction extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'chemical_stock_id', 'type', 'quantity', 'balance_after', 'reference_type', 'reference_id', 'notes'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }
}
