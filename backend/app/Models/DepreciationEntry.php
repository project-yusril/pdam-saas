<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DepreciationEntry — catatan penyusutan bulanan per aset. Fase 7.2 */
class DepreciationEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'fixed_asset_id', 'period', 'depreciation_amount',
        'accumulated_after', 'book_value_after', 'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'depreciation_amount' => 'decimal:2',
            'accumulated_after' => 'decimal:2',
            'book_value_after' => 'decimal:2',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }
}
