<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** InstallmentSchedule — jadwal termin cicilan. Fase 1.8 */
class InstallmentSchedule extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'plan_id', 'installment_no', 'amount', 'due_date',
        'status', 'paid_at', 'payment_id',
    ];

    protected function casts(): array
    {
        return [
            'installment_no' => 'integer',
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }
}
