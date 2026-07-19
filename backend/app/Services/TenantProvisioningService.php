<?php

namespace App\Services;

use App\Models\Module;
use App\Models\PdamOrganization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SubscriptionModule;
use App\Models\User;
use App\Notifications\TenantAdminInvitation;
use App\Support\RolePermissionPresets;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * TenantProvisioningService — provisioning PDAM baru (PRD 16.1 & 5.4).
 * Dijalankan Super-Admin. Sekali panggil membuat:
 *   1. Record pdam_organizations (tenant)
 *   2. Clone semua role template → role milik tenant
 *   3. Aktifkan modul default (CORE) via subscription_modules
 *   4. Buat user admin tenant + assign role admin_tenant
 * Semua dalam 1 transaksi (atomic).
 */
class TenantProvisioningService
{
    /**
     * @param  array{code:string,name:string,city?:string,province?:string}  $orgData
     * @param  array{name:string,email:string,password:string,phone?:string}  $adminData
     */
    public function provision(array $orgData, array $adminData): PdamOrganization
    {
        [$org, $admin] = DB::transaction(function () use ($orgData, $adminData) {
            // 1. Tenant

            $org = PdamOrganization::create([
                'code' => $orgData['code'],
                'name' => $orgData['name'],
                'city' => $orgData['city'] ?? null,
                'province' => $orgData['province'] ?? null,
                'contact_email' => $adminData['email'],
                'timezone' => $orgData['timezone'] ?? 'Asia/Jakarta',
                'subscription_status' => 'active',
            ]);

            // 2. Clone role template → role milik tenant
            $this->cloneRoles($org->id);

            // 3. Aktifkan modul default (CORE) + entitlement locked untuk sisanya
            $this->seedModuleEntitlements($org->id);

            // 4. User admin tenant
            $admin = new User([
                'name' => $adminData['name'],
                'email' => $adminData['email'],
                'phone' => $adminData['phone'] ?? null,
                'password' => Hash::make($adminData['password']),
                'is_tenant_admin' => true,
                'is_active' => true,
            ]);
            $admin->pdam_org_id = $org->id; // eksplisit (TenantContext belum di-set)
            $admin->save();

            $adminRole = Role::where('pdam_org_id', $org->id)
                ->where('code', 'admin_tenant')
                ->first();
            if ($adminRole) {
                $admin->roles()->attach($adminRole->id);
            }

            return [$org->fresh(), $admin];
        });

        // 5. Kirim email undangan (di luar transaksi; kegagalan mail tidak
        //    membatalkan provisioning yang sudah tersimpan).
        $this->sendInvitation($org, $admin);

        return $org;
    }

    /** Kirim email undangan ke admin tenant, aman terhadap kegagalan mailer. */
    protected function sendInvitation(PdamOrganization $org, User $admin): void
    {
        try {
            $admin->notify(new TenantAdminInvitation($org, $admin->name));
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim email undangan admin tenant.', [
                'pdam_org_id' => $org->id,
                'admin_email' => $admin->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Salin role template global (pdam_org_id null) menjadi role milik tenant. */
    protected function cloneRoles(int $orgId): void
    {
        $templates = Role::whereNull('pdam_org_id')
            ->where('is_system_default', true)
            ->get();

        // Peta code permission → id (sekali query)
        $permByCode = Permission::pluck('id', 'code');
        $presets = RolePermissionPresets::map();

        foreach ($templates as $tpl) {
            $newRole = Role::create([
                'pdam_org_id' => $orgId,
                'code' => $tpl->code,
                'name' => $tpl->name,
                'is_system_default' => true,
            ]);

            // admin_tenant otomatis dapat SEMUA permission (akses penuh tenant)
            if ($tpl->code === 'admin_tenant') {
                $newRole->permissions()->sync($permByCode->values()->all());

                continue;
            }

            // Role lain: terapkan preset (jika ada). Preset kosong = tanpa izin awal.
            if (! empty($presets[$tpl->code])) {
                $ids = collect($presets[$tpl->code])
                    ->map(fn ($code) => $permByCode[$code] ?? null)
                    ->filter()
                    ->values()
                    ->all();
                $newRole->permissions()->sync($ids);
            }
        }
    }

    /**
     * Buat entitlement modul: CORE aktif (default gratis), sisanya locked.
     */
    protected function seedModuleEntitlements(int $orgId): void
    {
        $modules = Module::where('is_active', true)->get();

        foreach ($modules as $module) {
            $isDefault = (bool) $module->is_default;

            SubscriptionModule::create([
                'pdam_org_id' => $orgId,
                'module_code' => $module->code,
                'status' => $isDefault ? 'active' : 'locked',
                'activation_method' => $isDefault ? 'free_default' : null,
                'amount_paid' => 0,
                'activated_at' => $isDefault ? now() : null,
                'expires_at' => null, // CORE tanpa kedaluwarsa
            ]);
        }
    }
}
