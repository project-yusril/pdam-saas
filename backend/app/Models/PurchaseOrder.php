<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** PurchaseOrder — pengadaan multi-level approval. Fase 4.2 */
class PurchaseOrder extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'po_number', 'supplier_id', 'requested_by', 'items',
        'total_estimated_price', 'urgency', 'status',
        'tech_approved_by', 'tech_approved_at', 'dir_approved_by', 'dir_approved_at',
        'fin_approved_by', 'fin_approved_at', 'purchased_at', 'received_at',
        'journal_entry_id', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'total_estimated_price' => 'decimal:2',
            'tech_approved_at' => 'datetime',
            'dir_approved_at' => 'datetime',
            'fin_approved_at' => 'datetime',
            'purchased_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }
}
