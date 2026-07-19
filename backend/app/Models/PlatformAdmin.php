<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * PlatformAdmin — Super-Admin (Platform Owner). Lintas tenant. PRD 5.4 & 16.1.
 */
class PlatformAdmin extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'platform_admins';

    protected $fillable = [
        'email',
        'full_name',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
