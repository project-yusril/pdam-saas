<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bill — tagihan bulanan. Fase 1.5 */
class Bill extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'customer_id', 'bill_number', 'period',
        'previous_reading_id', 'current_reading_id', 'previous_reading', 'current_reading',
        'consumption', 'water_charge', 'abonemen', 'meter_maintenance_fee',
        'admin_fee', 'penalty', 'amount_due', 'status', 'due_date', 'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'previous_reading' => 'integer',
            'current_reading' => 'integer',
            'consumption' => 'integer',
            'water_charge' => 'decimal:2',
            'abonemen' => 'decimal:2',
            'meter_maintenance_fee' => 'decimal:2',
            'admin_fee' => 'decimal:2',
            'penalty' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }
}
