<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** InstallmentPlanBill — tagihan tunggakan yang masuk rencana cicilan. Fase 1.8 */
class InstallmentPlanBill extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'plan_id', 'bill_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
