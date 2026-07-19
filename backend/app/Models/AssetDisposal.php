<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** AssetDisposal — pelepasan aset (jual/hapus/rusak) + laba-rugi. Fase 7.4 */
class AssetDisposal extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'fixed_asset_id', 'disposal_type', 'disposal_date',
        'sale_value', 'book_value_at_disposal', 'gain_loss', 'reason',
        'journal_entry_id', 'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'disposal_date' => 'date',
            'sale_value' => 'decimal:2',
            'book_value_at_disposal' => 'decimal:2',
            'gain_loss' => 'decimal:2',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }
}
