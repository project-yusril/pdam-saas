<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** StockAdjustment — penyesuaian stok (opname/rusak/hilang). Fase 4.4 */
class StockAdjustment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'material_id', 'warehouse_id', 'system_stock',
        'physical_stock', 'difference', 'reason', 'journal_entry_id', 'adjusted_by',
    ];

    protected function casts(): array
    {
        return [
            'system_stock' => 'decimal:2',
            'physical_stock' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }
}
