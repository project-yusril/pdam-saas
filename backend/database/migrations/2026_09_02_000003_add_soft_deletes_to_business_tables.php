<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft delete resource inti (Fase 2.x).
 *
 * Menambahkan `deleted_at` pada tabel bisnis utama agar tombol "Hapus" di
 * antarmuka berfungsi sebagai penghapusan lembut (restorable) tanpa melanggar
 * FK tenant (RESTRICT). Baris tetap ada di DB, hanya disembunyikan dari query.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['customers', 'tariff_categories', 'zones', 'meter_routes', 'customer_prospects'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (['customers', 'tariff_categories', 'zones', 'meter_routes', 'customer_prospects'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
