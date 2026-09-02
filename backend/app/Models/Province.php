<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Provinsi (master alamat global). Fase 1.1 */
class Province extends Model
{
    use SoftDeletes;

    protected $fillable = ['pdam_org_id', 'code', 'name'];

    /** Data yang "milik" tenant: global (pdam_org_id null) + punya tenant tsb. */
    public function scopeForTenant($query, ?int $orgId)
    {
        return $query->where(fn ($q) => $q->whereNull('pdam_org_id')->orWhere('pdam_org_id', $orgId));
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
