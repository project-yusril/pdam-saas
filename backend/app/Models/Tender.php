<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tender — Auto-generated dari skema tabel. */
class Tender extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'tender_number', 'title', 'description', 'category', 'budget_ceiling', 'publish_date', 'submission_deadline', 'award_date', 'status', 'winner_vendor_id', 'final_price'];

    protected function casts(): array
    {
        return [
            'budget_ceiling' => 'decimal:2',
            'publish_date' => 'datetime',
            'submission_deadline' => 'datetime',
            'award_date' => 'datetime',
            'final_price' => 'decimal:2',
        ];
    }

    public function bids(): HasMany
    {
        return $this->hasMany(TenderBid::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'winner_vendor_id');
    }
}
