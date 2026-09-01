<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** ChemicalQcTest — Auto-generated dari skema tabel. */
class ChemicalQcTest extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'chemical_receipt_id', 'test_name', 'result_value', 'result_unit', 'verdict', 'tested_by', 'notes'];

    protected function casts(): array
    {
        return [
            'result_value' => 'decimal:2',
        ];
    }
}

