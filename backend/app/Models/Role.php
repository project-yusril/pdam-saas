<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Role — role bawaan (template) & custom per tenant. PRD 16.2.
 * Template global disimpan dengan pdam_org_id null (tidak ter-scope saat
 * TenantContext kosong). Saat konteks tenant aktif, hanya role milik tenant terlihat.
 */
class Role extends Model
{
    use BelongsToTenant;

    protected $fillable = [

        'pdam_org_id',
        'code',
        'name',
        'is_system_default',
    ];

    protected function casts(): array
    {
        return [
            'is_system_default' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }
}
