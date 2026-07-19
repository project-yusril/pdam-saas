<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaasPurchaseOrderLine extends Model
{
    protected $fillable = ['saas_purchase_order_id', 'module_code', 'module_name', 'amount', 'dependencies'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'dependencies' => 'array'];
    }
}
