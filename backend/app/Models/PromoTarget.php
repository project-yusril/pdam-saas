<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoTarget extends Model
{
    public $timestamps = false;

    protected $fillable = ['promo_id', 'pdam_org_id'];

    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }
}
