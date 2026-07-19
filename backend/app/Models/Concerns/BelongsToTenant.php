<?php

namespace App\Models\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * BelongsToTenant — isolasi multi-tenant di level aplikasi (PRD 5).
 * MySQL tidak punya RLS, jadi setiap model bisnis pakai trait ini agar
 * otomatis ter-scope ke pdam_org_id yang aktif + auto-isi saat create.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // Auto-filter query berdasarkan tenant aktif
        static::addGlobalScope('tenant', function (Builder $builder) {
            $orgId = TenantContext::id();
            if ($orgId !== null) {
                $builder->where($builder->getModel()->getTable().'.pdam_org_id', $orgId);
            }
        });

        // Auto-isi pdam_org_id saat membuat record baru
        static::creating(function (Model $model) {
            if (empty($model->pdam_org_id) && TenantContext::id() !== null) {
                $model->pdam_org_id = TenantContext::id();
            }
        });
    }

    /** Query lintas tenant (khusus super-admin / job sistem). */
    public function scopeWithoutTenant(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }
}
