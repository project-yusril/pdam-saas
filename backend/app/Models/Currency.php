<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Currency — Auto-generated dari skema tabel. */
class Currency extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'code', 'name', 'symbol', 'decimal_places', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
