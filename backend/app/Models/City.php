<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Kota/Kabupaten. Fase 1.1 */
class City extends Model
{
    use SoftDeletes;

    protected $fillable = ['pdam_org_id', 'province_id', 'code', 'name', 'type'];

    public function scopeForTenant($query, ?int $orgId)
    {
        return $query->where(fn ($q) => $q->whereNull('pdam_org_id')->orWhere('pdam_org_id', $orgId));
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }
}
