<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NrwBalance extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'dma_zone_id', 'period',
        'system_input_m3', 'billed_metered_m3',
        'unbilled_metered_m3', 'unbilled_unmetered_m3',
        'authorised_consumption_m3', 'water_losses_m3',
        'apparent_losses_m3', 'real_losses_m3',
        'nrw_percentage', 'ili',
    ];

    protected function casts(): array
    {
        return [
            'system_input_m3' => 'decimal:2',
            'billed_metered_m3' => 'decimal:2',
            'unbilled_metered_m3' => 'decimal:2',
            'unbilled_unmetered_m3' => 'decimal:2',
            'authorised_consumption_m3' => 'decimal:2',
            'water_losses_m3' => 'decimal:2',
            'apparent_losses_m3' => 'decimal:2',
            'real_losses_m3' => 'decimal:2',
            'nrw_percentage' => 'decimal:2',
            'ili' => 'decimal:2',
        ];
    }

    public function dmaZone(): BelongsTo
    {
        return $this->belongsTo(DmaZone::class);
    }
}
