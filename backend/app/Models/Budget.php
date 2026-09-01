<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Budget — Auto-generated dari skema tabel. */
class Budget extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'name', 'fiscal_year', 'total_amount', 'status', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }
public function lines(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(BudgetLine::class); }
}

