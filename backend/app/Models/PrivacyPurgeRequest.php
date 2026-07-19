<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PrivacyPurgeRequest extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id',
        'pdam_org_id',
        'requested_by',
        'approved_by',
        'status',
        'target',
        'policy_version',
        'reason',
        'cutoff_at',
        'candidate_count',
        'deleted_count',
        'expires_at',
        'approved_at',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'cutoff_at' => 'datetime',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }
}
