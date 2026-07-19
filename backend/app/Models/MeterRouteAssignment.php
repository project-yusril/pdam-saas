<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MeterRouteAssignment — petugas ditugaskan ke rute. Fase 3.1 */
class MeterRouteAssignment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'meter_route_id', 'officer_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(MeterRoute::class, 'meter_route_id');
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }
}
