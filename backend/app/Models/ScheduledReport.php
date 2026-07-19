<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduledReport extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'created_by', 'name', 'dataset', 'format', 'columns', 'filters', 'sort', 'frequency', 'timezone', 'local_time', 'day_of_week', 'day_of_month', 'next_run_at', 'is_active'];

    protected function casts(): array
    {
        return ['columns' => 'array', 'filters' => 'array', 'next_run_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ScheduledReportRun::class);
    }
}
