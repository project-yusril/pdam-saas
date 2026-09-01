<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** BudgetLine — Auto-generated dari skema tabel. */
class BudgetLine extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'budget_id', 'account_code', 'project_code', 'cost_center_code', 'amount', 'realized'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'realized' => 'decimal:2',
        ];
    }
public function budget(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Budget::class); }
}

