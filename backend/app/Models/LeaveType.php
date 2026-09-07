<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** LeaveType — Auto-generated dari skema tabel. */
class LeaveType extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'code', 'name', 'default_quota', 'is_paid', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
