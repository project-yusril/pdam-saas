<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoRedemption extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'promo_id', 'pdam_org_id', 'module_code', 'original_price',
        'discount_amount', 'final_price', 'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'original_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'final_price' => 'decimal:2',
            'redeemed_at' => 'datetime',
        ];
    }

    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }
}
