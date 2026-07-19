<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MaintenanceSchedule extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'code', 'name', 'asset_type', 'asset_id',
        'frequency', 'interval_value', 'meter_hour_target',
        'next_due_date', 'last_completed_date', 'is_active', 'checklist_json',
    ];

    protected function casts(): array
    {
        return [
            'next_due_date' => 'date',
            'last_completed_date' => 'date',
            'is_active' => 'boolean',
            'checklist_json' => 'array',
        ];
    }
}
