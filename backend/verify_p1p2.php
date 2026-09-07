<?php

use App\Models\PdamOrganization;
use App\Models\Permission;
use App\Models\Role;

$org = PdamOrganization::first();
echo 'Tenant: '.$org->code.PHP_EOL;
echo 'Total permissions: '.Permission::count().PHP_EOL;
echo '--- Preset izin per role (tenant '.$org->code.') ---'.PHP_EOL;

foreach (['admin_tenant', 'director', 'finance_head', 'finance_staff', 'cashier', 'meter_officer', 'meter_office', 'survey_head', 'warehouse_head', 'customer'] as $c) {
    $r = Role::where('pdam_org_id', $org->id)->where('code', $c)->first();
    echo str_pad($c, 18).' => '.($r ? $r->permissions()->count() : 'N/A').' izin'.PHP_EOL;
}

echo '--- IAM permissions ---'.PHP_EOL;
foreach (Permission::where('module_code', 'IAM')->get() as $p) {
    echo '  '.$p->code.PHP_EOL;
}
