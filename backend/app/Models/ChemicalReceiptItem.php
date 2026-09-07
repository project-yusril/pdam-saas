<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ChemicalReceiptItem — Auto-generated dari skema tabel. */
class ChemicalReceiptItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'chemical_receipt_id', 'chemical_id', 'quantity', 'unit', 'unit_cost'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function chemical(): BelongsTo
    {
        return $this->belongsTo(Chemical::class);
    }
}
