<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CallLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'call_id', 'direction', 'caller_number', 'callee_number',
        'start_time', 'end_time', 'duration_seconds',
        'agent_id', 'customer_id', 'complaint_id',
        'disposition', 'notes', 'recording_url', 'status',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
