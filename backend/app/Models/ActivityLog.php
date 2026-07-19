<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ActivityLog — audit trail (PRD 0.7 & 16.12).
 * Tidak memakai BelongsToTenant agar pencatatan lintas konteks tetap jalan;
 * pdam_org_id diisi eksplisit dari TenantContext saat pembuatan.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null; // hanya created_at

    protected $fillable = [
        'pdam_org_id',
        'user_id',
        'actor_type',
        'action',
        'entity_type',
        'entity_id',
        'old_value',
        'new_value',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
