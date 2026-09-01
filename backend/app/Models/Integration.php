<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Integration — Auto-generated dari skema tabel. */
class Integration extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'name', 'provider', 'credentials', 'config', 'is_active'];

    protected function casts(): array
    {
        return [
            'credentials' => 'array',
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }
}

