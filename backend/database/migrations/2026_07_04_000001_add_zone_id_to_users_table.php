<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 — ZONE: tambah zone_id ke users agar pegawai bisa di-assign ke wilayah.
 * Nullable karena admin_tenant & customer tidak perlu wilayah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('zone_id')->nullable()->after('pdam_org_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('zone_id');
        });
    }
};
