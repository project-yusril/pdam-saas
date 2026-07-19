<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** TariffTier — tingkat harga per m³ dalam sebuah golongan. Fase 1.3 */
class TariffTier extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'tariff_category_id', 'tier_order',
        'min_usage', 'max_usage', 'price_per_m3', 'effective_date',
    ];

    protected function casts(): array
    {
        return [
            'tier_order' => 'integer',
            'min_usage' => 'integer',
            'max_usage' => 'integer',
            'price_per_m3' => 'decimal:2',
            'effective_date' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TariffCategory::class, 'tariff_category_id');
    }
}
