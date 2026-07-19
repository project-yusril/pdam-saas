<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Models\FixedAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * AstWebController — UI web Aset Tetap (Fase 7.4).
 * - dashboard: nilai perolehan vs buku, per kategori/status, aset akan lunas susut
 * - assets: daftar register aset (filter kategori/status)
 * - card: kartu aset (KIB) + histori penyusutan & mutasi
 */
class AstWebController extends Controller
{
    public function dashboard(): View
    {
        $totalCost = FixedAsset::whereIn('status', ['aktif', 'rusak'])->sum('acquisition_cost');
        $totalBook = FixedAsset::whereIn('status', ['aktif', 'rusak'])->sum('book_value');
        $totalAccum = FixedAsset::whereIn('status', ['aktif', 'rusak'])->sum('accumulated_depreciation');
        $assetCount = FixedAsset::count();

        $byCategory = FixedAsset::select('asset_category_id',
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(acquisition_cost) as cost'),
            DB::raw('SUM(book_value) as book'))
            ->with('category:id,code,name')
            ->groupBy('asset_category_id')
            ->get();

        $byStatus = FixedAsset::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')->pluck('total', 'status');

        return view('admin.ast.dashboard', compact(
            'totalCost', 'totalBook', 'totalAccum', 'assetCount', 'byCategory', 'byStatus'
        ));
    }

    public function assets(Request $request): View
    {
        $query = FixedAsset::with('category:id,code,name', 'zone:id,code,name')->orderBy('code');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($categoryId = $request->query('asset_category_id')) {
            $query->where('asset_category_id', $categoryId);
        }

        $assets = $query->paginate(25)->withQueryString();
        $categories = AssetCategory::orderBy('name')->get(['id', 'name']);

        return view('admin.ast.assets', compact('assets', 'categories'));
    }

    public function card(FixedAsset $fixedAsset): View
    {
        $fixedAsset->load('category', 'zone');
        $history = $fixedAsset->depreciationEntries()->orderBy('period')->get();
        $movements = $fixedAsset->movements()->orderBy('moved_at')->get();

        return view('admin.ast.card', compact('fixedAsset', 'history', 'movements'));
    }
}
