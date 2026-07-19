<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class PrivacyAuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'pdam_org_id',
        'privacy_purge_request_id',
        'actor_id',
        'event_type',
        'payload',
        'previous_hash',
        'event_hash',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Privacy audit events are append-only.'));
        static::deleting(fn () => throw new LogicException('Privacy audit events are append-only.'));
    }
}
