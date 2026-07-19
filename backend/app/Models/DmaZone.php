<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DmaZone extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'zone_id', 'code', 'name', 'boundary',
        'total_connections', 'base_demand_m3day', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'boundary' => 'json',
            'is_active' => 'boolean',
        ];
    }

    public function latestReading()
    {
        return $this->hasOne(DistributionReading::class)->latestOfMany('reading_at');
    }
}
