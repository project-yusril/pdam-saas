<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** ChemicalReceipt — Auto-generated dari skema tabel. */
class ChemicalReceipt extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'receipt_number', 'purchase_order_id', 'supplier_id', 'receipt_date', 'batch_number', 'expiry_date', 'status', 'total_cost'];

    protected function casts(): array
    {
        return [
            'receipt_date' => 'datetime',
            'expiry_date' => 'datetime',
            'total_cost' => 'decimal:2',
        ];
    }
public function items(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ChemicalReceiptItem::class); }
public function qcTests(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ChemicalQcTest::class); }
public function supplier(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Supplier::class); }
public function purchaseOrder(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
}

