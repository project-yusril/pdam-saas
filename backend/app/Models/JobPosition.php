<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class JobPosition extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'code', 'name', 'structural_level', 'min_salary', 'max_salary',
    ];

    protected function casts(): array
    {
        return [
            'min_salary' => 'decimal:2',
            'max_salary' => 'decimal:2',
        ];
    }
}
