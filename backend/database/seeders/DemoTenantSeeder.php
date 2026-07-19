<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Services\TenantProvisioningService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DemoTenantSeeder — data demo 2 tenant: PDAM Canada & PDAM Brazil (PRD 16.1).
 *
 * Untuk SETIAP tenant, dibuat 1 user demo per role (34 role).
 * Konvensi kredensial demo:
 *   - Email    : {role_code}@gmail.com   (mis. director@gmail.com)
 *   - Password : 12345678                (semua role)
 * Karena email unik PER tenant, email yang sama dipakai di kedua PDAM;
 * login membedakannya lewat kode PDAM (pdam-canada / pdam-brazil).
 */
class DemoTenantSeeder extends Seeder
{
    public const DEFAULT_PASSWORD = '12345678';

    /** Daftar tenant demo: [code, name, city, province]. */
    public const TENANTS = [
        ['pdam-canada', 'PDAM Canada', 'Ottawa', 'Ontario'],
        ['pdam-brazil', 'PDAM Brazil', 'Brasilia', 'Distrito Federal'],
    ];

    public function run(): void
    {
        /** @var TenantProvisioningService $svc */
        $svc = app(TenantProvisioningService::class);

        foreach (self::TENANTS as [$code, $name, $city, $province]) {
            // Provision tenant + admin_tenant@gmail.com (idempotent lewat cek existing)
            $org = \App\Models\PdamOrganization::where('code', $code)->first();
            if (! $org) {
                $org = $svc->provision(
                    orgData: [
                        'code' => $code,
                        'name' => $name,
                        'city' => $city,
                        'province' => $province,
                    ],
                    adminData: [
                        'name' => 'Admin ' . $name,
                        'email' => 'admin_tenant@gmail.com',
                        'password' => self::DEFAULT_PASSWORD,
                    ],
                );
            }

            // Buat 1 user demo untuk setiap role milik tenant ini
            $roles = Role::withoutGlobalScopes()
                ->where('pdam_org_id', $org->id)
                ->get();

            foreach ($roles as $role) {
                $email = $role->code . '@gmail.com';

                $user = User::withoutGlobalScopes()
                    ->where('pdam_org_id', $org->id)
                    ->where('email', $email)
                    ->first();

                if (! $user) {
                    $user = new User([
                        'name' => $this->roleDisplayName($role->code) . ' — ' . $name,
                        'email' => $email,
                        'password' => Hash::make(self::DEFAULT_PASSWORD),
                        'is_tenant_admin' => $role->code === 'admin_tenant',
                        'is_active' => true,
                    ]);
                    $user->pdam_org_id = $org->id;
                    $user->save();
                }

                // Pastikan role ter-assign
                if (! $user->roles()->where('roles.id', $role->id)->exists()) {
                    $user->roles()->attach($role->id);
                }
            }
        }
    }

    private function roleDisplayName(string $code): string
    {
        return RoleTemplateSeeder::ROLES[$code] ?? ucfirst($code);
    }
}
