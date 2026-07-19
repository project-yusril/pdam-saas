<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $tenantIds = DB::table('pdam_organizations')->pluck('id')->prepend(null);
        $permissionId = DB::table('permissions')->where('code', 'core.privacy.purge')->value('id');

        foreach ($tenantIds as $tenantId) {
            $query = DB::table('roles')->where('code', 'compliance_officer');
            $tenantId === null ? $query->whereNull('pdam_org_id') : $query->where('pdam_org_id', $tenantId);
            $roleId = $query->value('id');

            if (! $roleId) {
                $roleId = DB::table('roles')->insertGetId([
                    'pdam_org_id' => $tenantId,
                    'code' => 'compliance_officer',
                    'name' => 'Petugas Kepatuhan / Perlindungan Data',
                    'is_system_default' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($permissionId) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $roleIds = DB::table('roles')->where('code', 'compliance_officer')->pluck('id');
        DB::table('role_permissions')->whereIn('role_id', $roleIds)->delete();
        DB::table('user_roles')->whereIn('role_id', $roleIds)->delete();
        DB::table('roles')->whereIn('id', $roleIds)->delete();
    }
};
