<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PdamOrganization — tenant. PRD 5 & 16.1.
 */
class PdamOrganization extends Model
{
    protected $fillable = [
        'code',
        'name',
        'city',
        'province',
        'logo_url',
        'letterhead_config',
        'contact_phone',
        'contact_email',
        'timezone',
        'subscription_status',
    ];

    protected function casts(): array
    {
        return [
            'letterhead_config' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'pdam_org_id');
    }

    public function subscriptionModules(): HasMany
    {
        return $this->hasMany(SubscriptionModule::class, 'pdam_org_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'pdam_org_id');
    }

    public function saasInvoices(): HasMany
    {
        return $this->hasMany(SaasInvoice::class, 'pdam_org_id');
    }

    public function saasPurchaseOrders(): HasMany
    {
        return $this->hasMany(SaasPurchaseOrder::class, 'pdam_org_id');
    }
}
