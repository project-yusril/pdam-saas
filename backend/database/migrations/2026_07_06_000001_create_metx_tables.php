<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 6 — METX (Meter Analytics).
 * meters (master meter fisik) + meter_stock (meter belum terpasang, link WH) +
 * meter_anomalies (hasil deteksi rule-based konsumsi/manipulasi).
 * ERD PRD 16.13 blok E. Nilai bisnis: tekan NRW komersial.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Master meter fisik PDAM (terpasang / gudang / dicabut / afkir).
        Schema::create('meters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->string('serial_number');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('diameter', 10)->nullable(); // 1/2", 3/4", 1"
            $table->unsignedSmallInteger('install_year')->nullable();
            $table->date('install_date')->nullable();
            $table->string('condition')->default('baru'); // baru|baik|buram|macet|rusak
            $table->string('location_note')->nullable();
            $table->date('last_calibration_date')->nullable();
            $table->string('status')->default('gudang'); // gudang|terpasang|dicabut|afkir
            $table->string('tamper_status')->default('normal'); // normal|suspect|tampered|broken
            $table->date('warranty_until')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pdam_org_id', 'serial_number']);
            $table->index(['pdam_org_id', 'status']);
        });

        // Riwayat lifecycle meter (gudang → terpasang → dicabut → kalibrasi → afkir).
        Schema::create('meter_lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('meter_id')->constrained('meters')->cascadeOnDelete();
            $table->string('event'); // installed|removed|recalibrated|scrapped|stored|condition_changed
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->timestamps();
        });

        // Stok meter belum terpasang (link ke material WH kategori Meteran Air).
        Schema::create('meter_stock', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('meter_id')->nullable()->constrained('meters')->nullOnDelete();
            $table->string('brand')->nullable();
            $table->string('diameter', 10)->nullable();
            $table->string('status')->default('available'); // available|reserved|installed
            $table->timestamps();
        });

        // Hasil deteksi anomali per pelanggan per periode.
        Schema::create('meter_anomalies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('meter_id')->nullable()->constrained('meters')->nullOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->string('rule_code'); // spike|drop|zero_streak|repeated_estimate|permanent_drop|category_mismatch
            $table->string('severity')->default('medium'); // low|medium|high
            $table->decimal('expected_value', 12, 2)->nullable();
            $table->decimal('actual_value', 12, 2)->nullable();
            $table->string('description')->nullable();
            $table->string('status')->default('open'); // open|reviewing|confirmed|dismissed
            $table->string('resolution')->nullable(); // replace_meter|back_bill|sanction|no_action
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['pdam_org_id', 'customer_id', 'period', 'rule_code']);
            $table->index(['pdam_org_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_anomalies');
        Schema::dropIfExists('meter_stock');
        Schema::dropIfExists('meter_lifecycle_events');
        Schema::dropIfExists('meters');
    }
};
