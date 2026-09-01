<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** EmploymentTermination — Auto-generated dari skema tabel. */
class EmploymentTermination extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'employee_id', 'termination_date', 'reason_type', 'reason', 'severance_amount', 'other_compensation', 'status', 'approved_by'];

    protected function casts(): array
    {
        return [
            'termination_date' => 'datetime',
            'severance_amount' => 'decimal:2',
            'other_compensation' => 'decimal:2',
        ];
    }
public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(HrEmployee::class, 'employee_id'); }
}

