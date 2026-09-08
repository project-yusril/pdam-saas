<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PaymentRefund — bukti pengembalian dana sebuah Payment beserta jurnal
 * pembalikan. Fitur gated (PRD §23); `reverses_gateway` true menandai kas
 * sudah dibalik di sisi pembukuan (gateway manual).
 */
class PaymentRefund extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'payment_id', 'customer_id', 'amount', 'journal_entry_id',
        'approved_by', 'method', 'reverses_gateway', 'reason', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reverses_gateway' => 'boolean',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
