<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }
}
