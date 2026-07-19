<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SalesInvoiceItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'sales_invoice_id', 'description',
        'quantity', 'unit_price', 'amount', 'account_code',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }
}
