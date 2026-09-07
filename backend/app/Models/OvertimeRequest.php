<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** OvertimeRequest — Auto-generated dari skema tabel. */
class OvertimeRequest extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'employee_id', 'date', 'hours', 'reason', 'status', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'hours' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }
}
