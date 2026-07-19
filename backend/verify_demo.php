<?php

use App\Models\PdamOrganization;
use App\Models\PlatformAdmin;
use App\Models\User;

echo 'Orgs: ' . PdamOrganization::count() . PHP_EOL;
echo 'Users total: ' . User::withoutGlobalScopes()->count() . PHP_EOL;
foreach (PdamOrganization::all() as $o) {
    $c = User::withoutGlobalScopes()->where('pdam_org_id', $o->id)->count();
    echo $o->code . ' (' . $o->name . '): ' . $c . ' users' . PHP_EOL;
}
echo 'SuperAdmin: ' . PlatformAdmin::first()->email . PHP_EOL;
