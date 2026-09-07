<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** TariffCategory â€” golongan tarif (17 golongan). Fase 1.3 */
class TariffCategory extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'pdam_org_id', 'code', 'name', 'group_type', 'description',
        'abonemen', 'meter_maintenance_fee', 'admin_fee', 'minimum_usage_m3', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'abonemen' => 'decimal:2',
            'meter_maintenance_fee' => 'decimal:2',
            'admin_fee' => 'decimal:2',
            'minimum_usage_m3' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(TariffTier::class)->orderBy('tier_order');
    }
}
