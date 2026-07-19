<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1.4 — Pelanggan + riwayat status.
 * customers: sambungan air aktif. customer_status_history: jejak perubahan
 * status (active → isolir → reconnected → dst) untuk audit lifecycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();     // akun mobile (opsional)
            $table->unsignedBigInteger('prospect_id')->nullable(); // asal pendaftaran (Fase 2)
            $table->string('customer_number', 30);                 // nomor pelanggan unik
            $table->string('full_name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();

            // Alamat sambungan
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->unsignedBigInteger('street_id')->nullable();
            $table->string('address_detail')->nullable(); // no rumah, RT/RW
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->foreignId('tariff_category_id')->nullable()->constrained('tariff_categories')->nullOnDelete();
            $table->string('meter_serial_number', 50)->nullable();
            $table->unsignedBigInteger('meter_route_id')->nullable(); // Fase 3
            $table->date('installation_date')->nullable();
            $table->unsignedInteger('initial_reading')->default(0);

            $table->string('status')->default('active');
            // active | isolir | terminated | temporary_closed | inactive
            $table->timestamps();

            $table->unique(['pdam_org_id', 'customer_number']);
            $table->index(['pdam_org_id', 'status']);
        });

        Schema::create('customer_status_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_status_history');
        Schema::dropIfExists('customers');
    }
};
