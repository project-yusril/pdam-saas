<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Attendance — Auto-generated dari skema tabel. */
class Attendance extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'employee_id', 'date', 'check_in', 'check_out', 'check_in_lat', 'check_in_lng', 'status', 'source', 'notes'];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'check_in' => 'datetime',
            'check_out' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }
}
