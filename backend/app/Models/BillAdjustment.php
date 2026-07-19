<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillAdjustment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'bill_id', 'type', 'reason',
        'old_amount', 'new_amount', 'adjustment_amount',
        'requested_by', 'approved_by', 'status', 'journal_entry_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'old_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
