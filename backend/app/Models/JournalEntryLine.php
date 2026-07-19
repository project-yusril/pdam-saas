<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** JournalEntryLine — baris debit/kredit jurnal. Fase 1.6 */
class JournalEntryLine extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'journal_id', 'account_id', 'type', 'amount', 'memo',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
