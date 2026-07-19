<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1.3 — Tarif tiered (PRD 10.1) + pengaturan penagihan.
 * tariff_categories: 17 golongan (1A, 2A3, dst).
 * tariff_tiers: batas pemakaian + harga per m³ per golongan.
 * billing_settings: komponen tetap (abonemen, admin, pemeliharaan meter,
 *   denda, minimum charge) per tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariff_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->string('code', 20);   // 1A, 2A3, 5B
            $table->string('name');
            $table->string('group_type')->nullable(); // sosial|rumah_tangga|niaga|industri|khusus
            $table->text('description')->nullable();
            $table->decimal('abonemen', 12, 2)->default(0);          // biaya beban tetap /bulan
            $table->decimal('meter_maintenance_fee', 12, 2)->default(0); // pemeliharaan meter /bulan
            $table->decimal('admin_fee', 12, 2)->default(0);         // biaya administrasi /tagihan
            $table->unsignedInteger('minimum_usage_m3')->default(0); // pemakaian minimum ditagih
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['pdam_org_id', 'code']);
        });

        Schema::create('tariff_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('tariff_category_id')->constrained('tariff_categories')->cascadeOnDelete();
            $table->unsignedInteger('tier_order');       // 1,2,3
            $table->unsignedInteger('min_usage');        // batas bawah (m³) inklusif
            $table->unsignedInteger('max_usage')->nullable(); // batas atas; null = tak terbatas
            $table->decimal('price_per_m3', 12, 2);
            $table->date('effective_date')->nullable();
            $table->timestamps();

            $table->index(['tariff_category_id', 'tier_order']);
        });

        // Pengaturan penagihan global per tenant (denda, dll)
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->unique();
            $table->decimal('penalty_flat', 12, 2)->default(0);      // denda tetap Rp
            $table->decimal('penalty_percent', 5, 2)->default(0);    // denda % dari tagihan
            $table->unsignedInteger('due_day')->default(20);         // tanggal jatuh tempo
            $table->unsignedInteger('isolir_after_months')->default(3); // isolir setelah N bulan nunggak
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
        Schema::dropIfExists('tariff_tiers');
        Schema::dropIfExists('tariff_categories');
    }
};
