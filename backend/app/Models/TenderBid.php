<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** TenderBid — Auto-generated dari skema tabel. */
class TenderBid extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'tender_id', 'vendor_id', 'bid_price', 'technical_proposal', 'technical_score', 'price_score', 'total_score', 'rank'];

    protected function casts(): array
    {
        return [
            'bid_price' => 'decimal:2',
        ];
    }
public function tender(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Tender::class); }
public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Vendor::class); }
}

