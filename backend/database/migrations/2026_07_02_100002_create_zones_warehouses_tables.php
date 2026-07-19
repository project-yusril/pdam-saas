<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1.2 — Wilayah (zones) & Gudang (warehouses) per tenant.
 * Saat zone dibuat, sistem otomatis membuat 1 gudang buffer (lihat model Zone).
 * Gudang utama (is_main) hanya satu per tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->string('code', 20);            // W1, W2, ...
            $table->string('name');
            $table->string('office_address')->nullable();
            $table->string('office_phone', 30)->nullable();
            $table->boolean('is_main')->default(false); // kantor utama
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['pdam_org_id', 'code']);
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->string('warehouse_type')->default('buffer'); // main|buffer
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['pdam_org_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('zones');
    }
};
