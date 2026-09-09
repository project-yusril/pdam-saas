<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User — pengguna internal & pelanggan sebuah PDAM (tenant).
 * TERPISAH dari PlatformAdmin (super-admin). PRD 16.2.
 */
class User extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'pdam_org_id',
        'zone_id',
        'name',
        'email',
        'phone',
        'password',
        'is_tenant_admin',
        'is_active',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_tenant_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(PdamOrganization::class, 'pdam_org_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function permissionOverrides(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')
            ->withPivot('granted');
    }

    /** Lokasi GPS terakhir petugas (bila pernah lapor / submitSurvey ber-koordinat). */
    public function latestLocation()
    {
        return $this->hasOne(TechnicianLocation::class)->latestOfMany('updated_at');
    }

    /** Cek apakah user punya salah satu role (by code). */
    public function hasRole(string ...$codes): bool
    {
        return $this->roles()->whereIn('code', $codes)->exists();
    }

    /**
     * Cek permission efektif (RBAC + override per user).
     * Deny eksplisit (user_permissions.granted=false) mengalahkan role.
     */
    public function hasPermission(string $permissionCode): bool
    {
        // Override deny per user
        $override = $this->permissionOverrides()
            ->where('code', $permissionCode)
            ->first();
        if ($override) {
            return (bool) $override->pivot->granted;
        }

        // Dari role
        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('code', $permissionCode))
            ->exists();
    }
}
