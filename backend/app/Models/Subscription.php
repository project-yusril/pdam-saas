<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = ['pdam_org_id', 'plan_tier', 'start_date', 'end_date', 'status', 'billing_cycle'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(PdamOrganization::class, 'pdam_org_id');
    }
}
