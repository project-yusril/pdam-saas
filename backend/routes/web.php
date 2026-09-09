<?php

use App\Http\Controllers\Web\AstWebController;
use App\Http\Controllers\Web\GisWebController;
use App\Http\Controllers\Web\MeterRouteWebController;
use App\Http\Controllers\Web\MetxWebController;
use App\Http\Controllers\Web\NetworkWebController;
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

    // ── Jaringan Perpipaan (GIS) — editor + isolasi bocor + DMA + NRW ────
    Route::prefix('network')->name('admin.network.')->group(function () {
        Route::get('/', [NetworkWebController::class, 'index'])
            ->middleware('module:GIS')->name('index');
        Route::get('layers.json', [NetworkWebController::class, 'layers'])->middleware('permission:gis.feature.view')->name('layers');
        Route::get('officers.json', [NetworkWebController::class, 'officers'])->middleware('permission:gis.feature.view')->name('officers');
        Route::get('nrw.json', [NetworkWebController::class, 'nrwSummary'])->middleware('permission:gis.feature.view')->name('nrw');
        Route::post('features', [NetworkWebController::class, 'storeFeature'])->middleware('permission:gis.feature.create')->name('features.store');
        Route::patch('features/{feature}', [NetworkWebController::class, 'updateFeature'])->middleware('permission:gis.feature.update')->name('features.update');
        Route::delete('features/{feature}', [NetworkWebController::class, 'destroyFeature'])->middleware('permission:gis.feature.delete')->name('features.destroy');
        Route::post('edges', [NetworkWebController::class, 'storeEdge'])->middleware('permission:gis.feature.create')->name('edges.store');
        Route::delete('edges/{edge}', [NetworkWebController::class, 'destroyEdge'])->middleware('permission:gis.feature.delete')->name('edges.destroy');
        Route::post('isolate', [NetworkWebController::class, 'isolate'])->middleware('permission:gis.feature.view')->name('isolate');
        Route::post('incidents', [NetworkWebController::class, 'storeIncidentWorkOrder'])->middleware('permission:gis.feature.create')->name('incidents.store');
        Route::get('technicians.json', [NetworkWebController::class, 'technicians'])->middleware('permission:gis.feature.view')->name('technicians');
        Route::post('dispatch', [NetworkWebController::class, 'dispatch'])->middleware('permission:gis.feature.create')->name('dispatch');
        Route::post('dmas', [NetworkWebController::class, 'storeDma'])->middleware('permission:gis.feature.create')->name('dmas.store');
        Route::patch('dmas/{dma}', [NetworkWebController::class, 'updateDma'])->middleware('permission:gis.feature.update')->name('dmas.update');
        Route::delete('dmas/{dma}', [NetworkWebController::class, 'destroyDma'])->middleware('permission:gis.feature.delete')->name('dmas.destroy');
        Route::post('dmas/{dma}/nrw', [NetworkWebController::class, 'nrwCalculate'])->middleware('permission:gis.feature.update')->name('dmas.nrw');
    });

    // ── GIS — Peta Pelanggan OSM/Leaflet (direktur/admin) ──────────────
    Route::prefix('gis')->name('admin.gis.')->middleware('module:GIS')->group(function () {
        Route::get('map', [GisWebController::class, 'index'])->name('map');
        Route::get('customers.json', [GisWebController::class, 'customers'])->name('customers');
        Route::get('summary.json', [GisWebController::class, 'summary'])->name('summary');
        Route::get('no-coords.json', [GisWebController::class, 'noCoords'])->name('no-coords');
        Route::get('search.json', [GisWebController::class, 'search'])->middleware('throttle:20,1')->name('search');
        Route::get('route.json', [GisWebController::class, 'route'])->middleware('throttle:20,1')->name('route');
        Route::get('tour.json', [GisWebController::class, 'tour'])->middleware('throttle:10,1')->name('tour');
        Route::post('customers/{customer}/geocode', [GisWebController::class, 'geocode'])
            ->middleware('throttle:10,1')->name('geocode');
        Route::post('geocode-missing', [GisWebController::class, 'geocodeMissing'])
            ->middleware('throttle:5,1')->name('geocode-missing');
        Route::patch('customers/{customer}/coordinates', [GisWebController::class, 'setCoordinates'])
            ->name('coordinates');
    });

    // ── AST — Aset Tetap (dashboard, register, kartu KIB) ─────────────
    Route::get('ast/dashboard', [AstWebController::class, 'dashboard'])->name('admin.ast.dashboard');
    Route::get('ast/assets', [AstWebController::class, 'assets'])->name('admin.ast.assets');
    Route::get('ast/assets/{fixedAsset}', [AstWebController::class, 'card'])->name('admin.ast.card');
});
