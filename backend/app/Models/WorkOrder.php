<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'wo_number', 'type', 'priority', 'customer_id',
        'zone_id', 'asset_id', 'source_type', 'source_id', 'status',
        'sla_due_at', 'assigned_to', 'address', 'latitude', 'longitude',
        'description', 'photos_before', 'photos_after', 'resolution',
        'started_at', 'completed_at', 'customer_signature', 'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'sla_due_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'photos_before' => 'array',
            'photos_after' => 'array',
            'customer_signature' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WorkOrderLog::class);
    }
}
