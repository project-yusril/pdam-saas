<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** MaterialTransaction — log mutasi stok per gudang. Fase 4.1 */
class MaterialTransaction extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = [
        'pdam_org_id', 'material_id', 'warehouse_id', 'transaction_type',
        'quantity', 'balance_after', 'reference_type', 'reference_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'balance_after' => 'decimal:2'];
    }
}
