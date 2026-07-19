<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 — SRV (Survey & Pemasangan Baru).
 * customer_prospects: calon pelanggan (NIK dienkripsi di model).
 * survey_reports: laporan survey lapangan + keputusan Kepala Survey.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_prospects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('registration_number', 40);
            $table->text('nik')->nullable();          // ENCRYPTED (cast di model)
            $table->string('full_name');
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('address')->nullable();          // alamat KTP
            $table->string('installation_address');         // alamat pemasangan
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('ktp_photo_url')->nullable();
            $table->json('ktp_ocr_raw')->nullable();
            $table->foreignId('tariff_category_id')->nullable()->constrained('tariff_categories')->nullOnDelete();

            $table->string('status')->default('pending_review');
            // pending_review|surveying|survey_submitted|re_survey_needed|survey_approved|
            // rejected|payment_pending|payment_paid|payment_expired|
            // installation_scheduled|installed|active
            $table->unsignedBigInteger('assigned_surveyor_id')->nullable();
            $table->decimal('installation_fee', 16, 2)->nullable();
            $table->timestamp('payment_due_at')->nullable(); // timer eskalasi
            $table->string('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['pdam_org_id', 'registration_number']);
            $table->index(['pdam_org_id', 'status']);
        });

        Schema::create('survey_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('prospect_id')->constrained('customer_prospects')->cascadeOnDelete();
            $table->unsignedBigInteger('surveyor_id');
            $table->json('photo_house_urls')->nullable();
            $table->decimal('distance_to_main_pipe', 8, 2)->nullable(); // meter
            $table->string('building_condition')->nullable();
            $table->string('accessibility')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('estimated_materials')->nullable();
            $table->decimal('estimated_cost', 16, 2)->nullable();
            $table->string('recommendation')->nullable(); // feasible|not_feasible

            $table->unsignedBigInteger('reviewed_by')->nullable(); // Kepala Survey
            $table->string('review_status')->nullable();   // approved|rejected|re_survey
            $table->string('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        // Penjadwalan pemasangan
        Schema::create('installation_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('prospect_id')->constrained('customer_prospects')->cascadeOnDelete();
            $table->date('scheduled_date')->nullable();
            $table->unsignedBigInteger('technician_id')->nullable();
            $table->string('status')->default('scheduled'); // scheduled|installed|cancelled
            $table->json('result_photo_urls')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_schedules');
        Schema::dropIfExists('survey_reports');
        Schema::dropIfExists('customer_prospects');
    }
};
