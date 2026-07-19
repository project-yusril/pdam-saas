<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Warehouse — gudang utama/buffer per wilayah. Fase 1.2 */
class Warehouse extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'zone_id', 'code', 'name', 'warehouse_type', 'address', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(MaterialStock::class);
    }

    public function isMain(): bool
    {
        return $this->warehouse_type === 'main';
    }
}
