<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SubscriptionModule — entitlement modul per tenant (gerbang lock/unlock). PRD 4.D.2.
 */
class SubscriptionModule extends Model
{
    protected $fillable = [
        'pdam_org_id',
        'module_code',
        'status',
        'activation_method',
        'amount_paid',
        'payment_proof_url',
        'activated_by',
        'activated_at',
        'expires_at',
        'locked_by',
        'locked_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'locked_at' => 'datetime',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(PdamOrganization::class, 'pdam_org_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_code', 'code');
    }

    /** Modul aktif = status active DAN belum kedaluwarsa. */
    public function isActiveNow(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
