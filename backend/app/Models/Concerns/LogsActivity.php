<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * LogsActivity — audit trail otomatis (PRD 0.7).
 * Model yang memakai trait ini otomatis mencatat created/updated/deleted
 * ke tabel `activity_logs`, lengkap dengan pelaku, tenant, dan perubahan.
 *
 * Model bisa override properti $auditExclude untuk menyembunyikan field sensitif.
 */
trait LogsActivity
{
    /** Field yang tidak boleh masuk log (mis. password). */
    protected array $auditExcludeDefault = ['password', 'remember_token'];

    public static function bootLogsActivity(): void
    {
        static::created(fn (Model $m) => $m->recordActivity('created', null, $m->filterAudit($m->getAttributes())));
        static::updated(function (Model $m) {
            $new = $m->filterAudit($m->getChanges());
            $old = $m->filterAudit(array_intersect_key($m->getOriginal(), $m->getChanges()));
            $m->recordActivity('updated', $old, $new);
        });
        static::deleted(fn (Model $m) => $m->recordActivity('deleted', $m->filterAudit($m->getOriginal()), null));
    }

    /** Buang field sensitif dari array yang akan dicatat. */
    public function filterAudit(array $attributes): array
    {
        $exclude = array_merge(
            $this->auditExcludeDefault,
            property_exists($this, 'auditExclude') ? $this->auditExclude : []
        );
        foreach ($exclude as $key) {
            unset($attributes[$key]);
        }

        return $attributes;
    }

    public function recordActivity(string $action, ?array $oldValue, ?array $newValue): void
    {
        // Hindari rekursi: jangan log model ActivityLog sendiri
        if ($this instanceof ActivityLog) {
            return;
        }

        $user = auth()->user();

        ActivityLog::create([
            'pdam_org_id' => TenantContext::id(),
            'user_id' => $user?->getKey(),
            'actor_type' => $user ? class_basename($user) : 'system',
            'action' => $action,
            'entity_type' => static::class,
            'entity_id' => $this->getKey(),
            'old_value' => $oldValue ?: null,
            'new_value' => $newValue ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }
}
