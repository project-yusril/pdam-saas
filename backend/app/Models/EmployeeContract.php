<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class EmployeeContract extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'employee_id', 'contract_number', 'start_date', 'end_date', 'type', 'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }
}
