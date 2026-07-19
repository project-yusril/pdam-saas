<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** RepairOrder — perbaikan/kebocoran (material → beban). Fase 4.4 */
class RepairOrder extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'order_number', 'customer_id', 'description',
        'materials_used', 'warehouse_id', 'status', 'journal_entry_id',
        'created_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['materials_used' => 'array', 'completed_at' => 'datetime'];
    }
}
