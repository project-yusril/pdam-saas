<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** BillItem — rincian komponen tagihan. Fase 1.5 */
class BillItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'bill_id', 'component', 'label', 'quantity', 'unit_price', 'amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
