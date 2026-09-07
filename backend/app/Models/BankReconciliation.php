<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** BankReconciliation — Auto-generated dari skema tabel. */
class BankReconciliation extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'bank_account_id', 'statement_date', 'reconciliation_date', 'statement_balance', 'book_balance', 'difference', 'status', 'notes'];

    protected function casts(): array
    {
        return [
            'statement_date' => 'datetime',
            'reconciliation_date' => 'datetime',
            'statement_balance' => 'decimal:2',
            'book_balance' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}
