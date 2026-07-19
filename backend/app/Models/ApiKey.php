<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'name', 'key', 'scopes', 'rate_limit', 'expires_at', 'last_used_at', 'is_active'];

    protected function casts(): array
    {
        return ['scopes' => 'json', 'expires_at' => 'datetime', 'last_used_at' => 'datetime', 'is_active' => 'boolean'];
    }
}
