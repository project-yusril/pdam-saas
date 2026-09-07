<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** TaxRecord — Auto-generated dari skema tabel. */
class TaxRecord extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'tax_type', 'reference_type', 'reference_id', 'tax_number', 'tax_date', 'dpp', 'tax_amount', 'status', 'due_date', 'journal_entry_id'];

    protected function casts(): array
    {
        return [
            'tax_date' => 'datetime',
            'dpp' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'due_date' => 'datetime',
        ];
    }
}
