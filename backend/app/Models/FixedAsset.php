<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** FixedAsset — register aset tetap PDAM (KIB-like). Fase 7.1 */
class FixedAsset extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'asset_category_id', 'zone_id', 'code', 'name', 'description',
        'location_note', 'acquisition_date', 'acquisition_cost', 'residual_value',
        'useful_life_months', 'depreciation_method', 'declining_rate', 'is_depreciable',
        'accumulated_depreciation', 'book_value', 'source', 'status',
        'document_number', 'photo_url', 'last_depreciated_period',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'residual_value' => 'decimal:2',
            'useful_life_months' => 'integer',
            'declining_rate' => 'decimal:2',
            'is_depreciable' => 'boolean',
            'accumulated_depreciation' => 'decimal:2',
            'book_value' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function depreciationEntries(): HasMany
    {
        return $this->hasMany(DepreciationEntry::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class);
    }

    /** Nilai yang dapat disusutkan = perolehan − nilai residu. */
    public function depreciableBase(): float
    {
        return max(0, (float) $this->acquisition_cost - (float) $this->residual_value);
    }

    /** Sudah lunas susut bila nilai buku sudah mencapai (atau di bawah) residu. */
    public function isFullyDepreciated(): bool
    {
        return (float) $this->book_value <= (float) $this->residual_value + 0.001;
    }
}
