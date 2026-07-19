<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Material — item gudang. Fase 4.1 */
class Material extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'code', 'name', 'category', 'unit', 'last_price', 'is_active',
    ];

    protected function casts(): array
    {
        return ['last_price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(MaterialStock::class);
    }
}
