<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRecord extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'schedule_id', 'work_order_id', 'execution_date',
        'technician_id', 'findings', 'cost_labor', 'cost_material',
        'outcome', 'recommendations', 'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'execution_date' => 'date',
            'cost_labor' => 'decimal:2',
            'cost_material' => 'decimal:2',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class, 'schedule_id');
    }
}
