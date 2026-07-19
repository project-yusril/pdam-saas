<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** InstallmentPlan — rencana cicilan tunggakan. Fase 1.8 */
class InstallmentPlan extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'customer_id', 'plan_number', 'total_amount', 'tenor_months',
        'monthly_amount', 'status', 'created_by', 'approved_by', 'director_approved_by',
        'approved_at', 'director_approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'monthly_amount' => 'decimal:2',
            'tenor_months' => 'integer',
            'approved_at' => 'datetime',
            'director_approved_at' => 'datetime',
        ];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(InstallmentSchedule::class, 'plan_id');
    }

    public function planBills(): HasMany
    {
        return $this->hasMany(InstallmentPlanBill::class, 'plan_id');
    }
}
