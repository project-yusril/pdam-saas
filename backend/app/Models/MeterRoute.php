<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** MeterRoute — rute baca meter. Fase 3.1 */
class MeterRoute extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'zone_id', 'code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function streets(): BelongsToMany
    {
        return $this->belongsToMany(Street::class, 'meter_route_streets');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MeterRouteAssignment::class);
    }
}
