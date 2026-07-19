<?php

use App\Http\Controllers\Web\AstWebController;
use App\Http\Controllers\Web\MeterRouteWebController;
use App\Http\Controllers\Web\MetxWebController;

use App\Http\Controllers\Web\ProspectWebController;
use App\Http\Controllers\Web\ZoneWebController;

use Illuminate\Support\Facades\Route;

// Root → aplikasi web (Vue SPA). Halaman login tampil otomatis bila belum auth.
Route::get('/', function () {
    return view('welcome');
});


// ── Admin Web UI — Zone Management ────────────────────────────────────
Route::middleware(['auth:sanctum', 'tenant'])->prefix('admin')->group(function () {
    Route::get('zones', [ZoneWebController::class, 'index'])->name('admin.zones.index');
    Route::get('zones/{zone}', [ZoneWebController::class, 'show'])->name('admin.zones.show');

    // ── SRV — Pendaftaran & Survey (hublang & survey_head) ────────────
    Route::get('prospects', [ProspectWebController::class, 'index'])->name('admin.prospects.index');
    Route::get('prospects/{prospect}', [ProspectWebController::class, 'show'])->name('admin.prospects.show');

    // ── MTR — Rute Baca Meter & Dashboard Progress ────────────────────
    Route::get('meter-routes', [MeterRouteWebController::class, 'index'])->name('admin.meter-routes.index');
    Route::get('meter-routes/dashboard', [MeterRouteWebController::class, 'dashboard'])->name('admin.meter-routes.dashboard');

    // ── METX — Meter Analytics (meter fisik, anomali, dashboard) ──────
    Route::get('metx/dashboard', [MetxWebController::class, 'dashboard'])->name('admin.metx.dashboard');
    Route::get('metx/meters', [MetxWebController::class, 'meters'])->name('admin.metx.meters');
    Route::get('metx/anomalies', [MetxWebController::class, 'anomalies'])->name('admin.metx.anomalies');

    // ── AST — Aset Tetap (dashboard, register, kartu KIB) ─────────────
    Route::get('ast/dashboard', [AstWebController::class, 'dashboard'])->name('admin.ast.dashboard');
    Route::get('ast/assets', [AstWebController::class, 'assets'])->name('admin.ast.assets');
    Route::get('ast/assets/{fixedAsset}', [AstWebController::class, 'card'])->name('admin.ast.card');
});


