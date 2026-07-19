<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 — MTR (Baca Meter Digital + Rute).
 * meter_routes + assignment jalan & petugas. reading_periods (buka/tutup).
 * meter_readings (fakta pembacaan + edge case). meter_replacements (ganti meter).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meter_routes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['pdam_org_id', 'code']);
        });

        Schema::create('meter_route_streets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('meter_route_id')->constrained('meter_routes')->cascadeOnDelete();
            $table->foreignId('street_id')->constrained('streets')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['meter_route_id', 'street_id']);
        });

        Schema::create('meter_route_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('meter_route_id')->constrained('meter_routes')->cascadeOnDelete();
            $table->unsignedBigInteger('officer_id'); // user meter_officer
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('reading_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->string('period', 7); // YYYY-MM
            $table->string('status')->default('open'); // open|closed
            $table->timestamp('opened_at')->nullable();
            $table->unsignedBigInteger('opened_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamps();

            $table->unique(['pdam_org_id', 'period']);
        });

        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('period', 7);
            $table->unsignedInteger('reading_value'); // angka meter (m³)
            $table->date('reading_date');
            $table->string('photo_house_url')->nullable();
            $table->string('photo_meter_url')->nullable();
            $table->string('reading_type')->default('ocr_confirmed');
            // ocr_confirmed|manual_corrected|estimated
            $table->string('unreadable_reason')->nullable(); // jika estimated
            $table->boolean('is_flagged')->default(false);
            $table->string('flag_reason')->nullable();
            $table->boolean('is_rollover')->default(false); // meter putar balik
            $table->unsignedBigInteger('read_by')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['pdam_org_id', 'customer_id', 'period']);
            $table->index(['pdam_org_id', 'period']);
        });

        Schema::create('meter_replacements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('old_serial')->nullable();
            $table->string('new_serial');
            $table->unsignedInteger('old_final_reading')->default(0);
            $table->unsignedInteger('new_initial_reading')->default(0);
            $table->date('replaced_at');
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_replacements');
        Schema::dropIfExists('meter_readings');
        Schema::dropIfExists('reading_periods');
        Schema::dropIfExists('meter_route_assignments');
        Schema::dropIfExists('meter_route_streets');
        Schema::dropIfExists('meter_routes');
    }
};
