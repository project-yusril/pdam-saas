<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Kecamatan. Fase 1.1 */
class District extends Model
{
    use SoftDeletes;

    protected $fillable = ['pdam_org_id', 'city_id', 'code', 'name'];

    public function scopeForTenant($query, ?int $orgId)
    {
        return $query->where(fn ($q) => $q->whereNull('pdam_org_id')->orWhere('pdam_org_id', $orgId));
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function villages(): HasMany
    {
        return $this->hasMany(Village::class);
    }
}
