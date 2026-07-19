<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Complaint extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'customer_id', 'ticket_number', 'category', 'priority',
        'subject', 'description', 'attachments', 'status', 'assigned_to',
        'sla_due_at', 'resolved_at', 'resolved_by', 'resolution',
        'repair_order_id', 'work_order_id',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'sla_due_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(ComplaintTrack::class);
    }
}
