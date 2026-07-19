<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Reconnection — penyambungan kembali (buka isolir). Fase 2.6 */
class Reconnection extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'customer_id', 'disconnection_id', 'fee', 'effective_date', 'processed_by',
    ];

    protected function casts(): array
    {
        return ['fee' => 'decimal:2', 'effective_date' => 'date'];
    }
}
