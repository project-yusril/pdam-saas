<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Payment — transaksi pembayaran. Fase 1.7 */
class Payment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'payment_type', 'bill_id', 'prospect_id',
        'installment_schedule_id', 'customer_id', 'payment_number', 'amount',
        'payment_method', 'channel', 'midtrans_order_id', 'midtrans_transaction_id',
        'status', 'paid_at', 'expired_at', 'received_by', 'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
