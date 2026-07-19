<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasPurchaseOrder extends Model
{
    use BelongsToTenant;

    protected $fillable = ['order_number', 'pdam_org_id', 'requested_by', 'idempotency_key', 'status', 'amount', 'gateway', 'gateway_transaction_id', 'payment_url', 'settled_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'settled_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaasPurchaseOrderLine::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(PdamOrganization::class, 'pdam_org_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
