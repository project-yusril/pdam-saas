<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MeterAnomaly — hasil deteksi anomali/manipulasi konsumsi meter. Fase 6.2 */
class MeterAnomaly extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'customer_id', 'meter_id', 'period', 'rule_code',
        'severity', 'expected_value', 'actual_value', 'description',
        'status', 'resolution', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_value' => 'decimal:2',
            'actual_value' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
