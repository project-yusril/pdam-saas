<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ProductionLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'production_date', 'source_id',
        'raw_water_m3', 'treated_water_m3', 'distributed_water_m3',
        'pump_runtime_hours', 'power_consumption_kwh',
        'turbidity_ntu', 'ph', 'chlorine_residual',
        'status', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'production_date' => 'date',
            'raw_water_m3' => 'decimal:2',
            'treated_water_m3' => 'decimal:2',
            'distributed_water_m3' => 'decimal:2',
            'pump_runtime_hours' => 'decimal:2',
            'power_consumption_kwh' => 'decimal:2',
            'turbidity_ntu' => 'decimal:2',
            'ph' => 'decimal:2',
            'chlorine_residual' => 'decimal:4',
        ];
    }
}
