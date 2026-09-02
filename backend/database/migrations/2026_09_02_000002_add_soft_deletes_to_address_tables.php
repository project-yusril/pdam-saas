<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft delete master alamat (Fase 1.1).
 *
 * Menambahkan kolom `deleted_at` ke lima tabel alamat agar penghapusan bersifat
 * lembut (baris tetap ada, hanya disembunyikan). Ini menjaga referensi (mis.
 * customers.street_id) tetap valid secara relasional saat audit/rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['provinces', 'cities', 'districts', 'villages', 'streets'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (['provinces', 'cities', 'districts', 'villages', 'streets'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
