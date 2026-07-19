<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** StockTransferItem — detail material per transfer. Fase 4.3 */
class StockTransferItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'transfer_id', 'material_id',
        'quantity_requested', 'quantity_received',
    ];

    protected function casts(): array
    {
        return ['quantity_requested' => 'decimal:2', 'quantity_received' => 'decimal:2'];
    }
}
