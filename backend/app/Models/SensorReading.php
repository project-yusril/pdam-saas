<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** SensorReading — Auto-generated dari skema tabel. */
class SensorReading extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'meter_id', 'customer_id', 'value', 'flow_rate', 'pressure', 'battery', 'signal_strength', 'reading_at', 'source', 'device_id', 'validated'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'flow_rate' => 'decimal:2',
            'pressure' => 'decimal:2',
            'battery' => 'decimal:2',
            'signal_strength' => 'decimal:2',
            'reading_at' => 'datetime',
            'validated' => 'boolean',
        ];
    }
    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function meter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }
}

