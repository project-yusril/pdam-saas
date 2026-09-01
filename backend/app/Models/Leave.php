<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Leave — Auto-generated dari skema tabel. */
class Leave extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'employee_id', 'leave_type_id', 'start_date', 'end_date', 'duration_days', 'balance_remaining', 'status', 'approved_by', 'approved_at', 'attachment_url', 'reason'];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'duration_days' => 'decimal:2',
            'balance_remaining' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }
public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(HrEmployee::class, 'employee_id'); }
public function leaveType(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(LeaveType::class, 'leave_type_id'); }
}

