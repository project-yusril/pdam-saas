<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** JournalEntry — header jurnal double-entry. Fase 1.6 */
class JournalEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'entry_number', 'entry_date', 'period', 'description',
        'reference_type', 'reference_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'journal_id');
    }
}
