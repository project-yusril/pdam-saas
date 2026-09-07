<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Chemical — Auto-generated dari skema tabel. */
class Chemical extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'code', 'name', 'unit', 'standard_dosage', 'safety_threshold', 'msds_url', 'is_active'];

    protected function casts(): array
    {
        return [
            'standard_dosage' => 'decimal:2',
            'safety_threshold' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
