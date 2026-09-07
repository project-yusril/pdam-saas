<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class PrivacyAuditEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * Produksi (gate least-privilege): bila DB_AUDIT_USERNAME diset, seluruh
     * akses event ini memakai connection `audit` milik akun pdam_audit yang
     * hanya memegang INSERT+SELECT pada tabel ini. Tanpa env tsb (dev/test)
     * connection default dipakai — trigger MySQL tetap menegakkan append-only.
     */
    public function getConnectionName()
    {
        if ($this->connection === null && filled(env('DB_AUDIT_USERNAME'))) {
            return 'audit';
        }

        return $this->connection;
    }

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
