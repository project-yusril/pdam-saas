<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** InstallationSchedule — jadwal & pelaksanaan pemasangan. Fase 2.6 */
class InstallationSchedule extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'prospect_id', 'scheduled_date', 'technician_id',
        'status', 'result_photo_urls', 'installed_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'result_photo_urls' => 'array',
            'installed_at' => 'datetime',
        ];
    }
}
