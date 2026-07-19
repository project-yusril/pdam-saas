<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceAppraisal extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'employee_id', 'period', 'score', 'kpi_data', 'evaluator_id', 'comments', 'status'];

    protected function casts(): array
    {
        return ['score' => 'decimal:2', 'kpi_data' => 'json'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }
}
