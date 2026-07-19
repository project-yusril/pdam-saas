<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1.1 — Struktur alamat berjenjang (PRD CORE).
 * provinces → cities → districts → villages → streets.
 * Data master alamat bersifat global (bukan per-tenant) agar bisa dipakai
 * semua PDAM. Tetapi `streets` diberi pdam_org_id opsional bila PDAM ingin
 * menambah jalan lokal sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();   // kode BPS/wilayah
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained('provinces')->cascadeOnDelete();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('type')->default('kabupaten'); // kota|kabupaten
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->string('code', 15)->unique();
            $table->string('name'); // kecamatan
            $table->timestamps();
        });

        Schema::create('villages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name'); // kelurahan/desa
            $table->string('postal_code', 10)->nullable();
            $table->timestamps();
        });

        Schema::create('streets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->nullable()->index(); // null = global
            $table->foreignId('village_id')->constrained('villages')->cascadeOnDelete();
            $table->string('name'); // nama jalan / blok
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['village_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streets');
        Schema::dropIfExists('villages');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('provinces');
    }
};
