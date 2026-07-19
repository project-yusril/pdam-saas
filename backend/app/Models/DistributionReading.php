<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DistributionReading extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'dma_zone_id', 'reading_at',
        'flow_rate_m3h', 'pressure_bar',
        'reservoir_level_percent', 'chlorine_residual',
    ];

    protected function casts(): array
    {
        return [
            'reading_at' => 'datetime',
            'flow_rate_m3h' => 'decimal:2',
            'pressure_bar' => 'decimal:2',
            'reservoir_level_percent' => 'decimal:2',
            'chlorine_residual' => 'decimal:4',
        ];
    }
}
