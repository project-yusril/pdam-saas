<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MeterReading — fakta pembacaan meter. Fase 3.2 */
class MeterReading extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'client_uuid', 'customer_id', 'period', 'reading_value', 'reading_date',
        'photo_house_url', 'photo_meter_url', 'reading_type', 'unreadable_reason',
        'is_flagged', 'flag_reason', 'is_rollover', 'read_by', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'reading_value' => 'integer',
            'reading_date' => 'date',
            'is_flagged' => 'boolean',
            'is_rollover' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'read_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
