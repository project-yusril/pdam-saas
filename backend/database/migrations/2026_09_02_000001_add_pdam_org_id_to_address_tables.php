<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1.1 — Per-tenant address customization.
 *
 * Data alamat berjenjang (province→city→district→village→street) memakai pola
 * "shared global reference + override per tenant":
 *   - `pdam_org_id = null`  → data reference global (dipakai semua PDAM).
 *   - `pdam_org_id = <id>`  → data milik tenant tersebut (custom/pribadi).
 *
 * Street memang sudah punya `pdam_org_id`; migrasi ini menambah kolom serupa
 * pada level di atasnya agar setiap PDAM bisa mengelola data alamatnya sendiri
 * tanpa mengubah reference global. Semua kolom nullable (additive, aman).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['provinces', 'cities', 'districts', 'villages'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('pdam_org_id')->nullable()->index()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach (['provinces', 'cities', 'districts', 'villages'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex(['pdam_org_id']);
                $table->dropColumn('pdam_org_id');
            });
        }
    }
};
