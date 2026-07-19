<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** StockTransfer — transfer stok (main→buffer / buffer→buffer). Fase 4.3 */
class StockTransfer extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'transfer_number', 'transfer_type', 'from_warehouse_id',
        'to_warehouse_id', 'reason', 'status', 'reference_order_id',
        'requested_by', 'approved_by', 'received_by', 'notes', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class, 'transfer_id');
    }
}
