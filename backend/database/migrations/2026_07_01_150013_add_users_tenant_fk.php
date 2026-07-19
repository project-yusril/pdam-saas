<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahkan FK pdam_org_id → pdam_organizations pada users, roles, employees, activity_logs.
 * Dipisah karena pdam_organizations dibuat setelah tabel users bawaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations')->cascadeOnDelete();
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations')->cascadeOnDelete();
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['pdam_org_id']);
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['pdam_org_id']);
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['pdam_org_id']);
        });
    }
};
