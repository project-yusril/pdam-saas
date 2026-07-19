<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use Illuminate\View\View;

class ZoneWebController extends Controller
{
    public function index(): View
    {
        $zones = Zone::withCount('customers', 'employees')
            ->with('warehouses:id,zone_id,code,name,warehouse_type,is_active')
            ->orderBy('is_main', 'desc')
            ->orderBy('code')
            ->get();

        return view('admin.zones.index', compact('zones'));
    }

    public function show(Zone $zone): View
    {
        $zone->load('warehouses', 'employees:id,zone_id,name,email,phone,is_active')
            ->loadCount('customers', 'employees');

        return view('admin.zones.show', compact('zone'));
    }
}
