<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * ModuleCatalogController — katalog modul global (PRD 4.C).
 */
class ModuleCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        $modules = Module::where('is_active', true)
            ->orderBy('tier')
            ->orderBy('code')
            ->get();

        return ApiResponse::success($modules);
    }
}
