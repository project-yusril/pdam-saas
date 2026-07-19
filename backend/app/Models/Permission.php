<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permission — granular per modul, pola modul.resource.aksi. PRD 4.D.3 & 16.2.
 */
class Permission extends Model
{
    protected $fillable = [
        'code',
        'module_code',
        'resource',
        'action',
        'description',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
