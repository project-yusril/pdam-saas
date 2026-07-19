<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceChangeLog extends Model
{
    protected $fillable = ['module_code', 'old_price', 'new_price', 'changed_by', 'changed_at'];

    protected function casts(): array
    {
        return ['old_price' => 'decimal:2', 'new_price' => 'decimal:2', 'changed_at' => 'datetime'];
    }
}
