<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrEmployee extends Model
{
    use BelongsToTenant;

    protected $table = 'hr_employees';

    protected $fillable = [
        'pdam_org_id', 'user_id', 'zone_id', 'position_id', 'unit_id', 'grade_id',
        'nip', 'nik', 'npwp', 'no_bpjs_kesehatan', 'no_bpjs_tk',
        'name', 'gender', 'birth_date', 'phone', 'email', 'address',
        'education', 'employment_status', 'tax_status', 'dependents',
        'bank_name', 'bank_account', 'join_date', 'resign_date', 'status', 'photo_url',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'join_date' => 'date',
            'resign_date' => 'date',
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class, 'position_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'unit_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(EmployeeGrade::class, 'grade_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
