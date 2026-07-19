<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class OrganizationUnit extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'code', 'name', 'parent_id', 'head_employee_id', 'zone_id', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
