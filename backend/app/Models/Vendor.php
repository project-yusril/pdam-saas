<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $fillable = [
        'pdam_org_id', 'code', 'name', 'npwp', 'contact_name',
        'phone', 'email', 'address', 'category', 'rating',
        'is_blacklisted', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'is_blacklisted' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
