<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Shift — Auto-generated dari skema tabel. */
class Shift extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'name', 'start_time', 'end_time', 'is_overnight', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_overnight' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}

