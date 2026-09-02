<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jalan/Blok. Bisa global (pdam_org_id null) atau lokal per tenant. Fase 1.1 */
class Street extends Model
{
    protected $fillable = ['pdam_org_id', 'village_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeForTenant($query, ?int $orgId)
    {
        return $query->where(fn ($q) => $q->whereNull('pdam_org_id')->orWhere('pdam_org_id', $orgId));
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }
}
