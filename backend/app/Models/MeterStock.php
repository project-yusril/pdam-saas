<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MeterStock — meter belum terpasang di gudang (link WH kategori Meteran Air). Fase 6.1 */
class MeterStock extends Model
{
    use BelongsToTenant;

    protected $table = 'meter_stock';

    protected $fillable = [
        'pdam_org_id', 'warehouse_id', 'material_id', 'meter_id',
        'brand', 'diameter', 'status',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }
}
