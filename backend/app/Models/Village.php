<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kelurahan/Desa. Fase 1.1 */
class Village extends Model
{
    protected $fillable = ['district_id', 'code', 'name', 'postal_code'];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function streets(): HasMany
    {
        return $this->hasMany(Street::class);
    }
}
