<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** RecurringTransaction — Auto-generated dari skema tabel. */
class RecurringTransaction extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'name', 'frequency', 'amount', 'journal_lines', 'next_run_date', 'is_active', 'last_entry_id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'journal_lines' => 'array',
            'next_run_date' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
