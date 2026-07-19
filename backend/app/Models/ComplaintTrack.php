<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintTrack extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'complaint_id', 'from_status', 'to_status',
        'action', 'note', 'user_id',
    ];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
