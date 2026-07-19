<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** BillingSetting — konfigurasi denda & jatuh tempo per tenant. Fase 1.3 */
class BillingSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'penalty_flat', 'penalty_percent',
        'due_day', 'isolir_after_months',
    ];

    protected function casts(): array
    {
        return [
            'penalty_flat' => 'decimal:2',
            'penalty_percent' => 'decimal:2',
            'due_day' => 'integer',
            'isolir_after_months' => 'integer',
        ];
    }
}
