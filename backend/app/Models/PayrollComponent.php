<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** PayrollComponent — Auto-generated dari skema tabel. */
class PayrollComponent extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'code', 'name', 'type', 'is_default', 'default_amount', 'default_percent'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'default_amount' => 'decimal:2',
            'default_percent' => 'decimal:2',
        ];
    }
}

