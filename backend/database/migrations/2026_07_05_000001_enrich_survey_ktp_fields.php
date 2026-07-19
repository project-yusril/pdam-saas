<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 — SRV: lengkapi field data e-KTP di customer_prospects +
 * Two-Stage GPS (customer_pin) + tipe file KTP + rincian alamat berjenjang.
 * survey_reports: tambah land_status + location_source (surveyor_verified).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_prospects', function (Blueprint $table) {
            // Data e-KTP lengkap (sesuai e-KTP)
            $table->string('gender', 1)->nullable()->after('birth_date');            // L|P
            $table->string('religion', 30)->nullable()->after('gender');
            $table->string('marital_status', 20)->nullable()->after('religion');      // belum_kawin|kawin|cerai_hidup|cerai_mati
            $table->string('occupation', 100)->nullable()->after('marital_status');
            $table->string('blood_type', 3)->nullable()->after('occupation');         // A|B|AB|O (opsional)
            $table->string('nationality', 30)->default('WNI')->after('blood_type');

            // Alamat berjenjang (kelurahan/desa → kecamatan → kota → provinsi via street)
            $table->unsignedBigInteger('street_id')->nullable()->after('installation_address');
            $table->string('house_number', 20)->nullable()->after('street_id');
            $table->string('rt', 5)->nullable()->after('house_number');
            $table->string('rw', 5)->nullable()->after('rt');

            // Two-Stage GPS Tahap 1 (map picker saat daftar)
            $table->decimal('latitude', 10, 7)->nullable()->after('rw');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('location_source', 20)->nullable()->after('longitude');    // customer_pin|surveyor_verified
            $table->decimal('location_accuracy', 8, 2)->nullable()->after('location_source'); // meter

            // Upload KTP: JPG/PNG/PDF
            $table->string('ktp_file_type', 10)->nullable()->after('ktp_photo_url');   // jpg|png|pdf

            // Rincian biaya pemasangan (material + jasa) — JSON breakdown
            $table->json('installation_fee_breakdown')->nullable()->after('installation_fee');
        });

        Schema::table('survey_reports', function (Blueprint $table) {
            // Penilaian kelayakan tambahan
            $table->string('land_status', 30)->nullable()->after('accessibility');     // milik_sendiri|sewa|lainnya
            $table->text('surveyor_notes')->nullable()->after('recommendation');

            // Two-Stage GPS Tahap 2 (koreksi surveyor → koordinat FINAL)
            $table->string('location_source', 20)->nullable()->after('longitude');     // surveyor_verified
            $table->decimal('location_accuracy', 8, 2)->nullable()->after('location_source');
        });
    }

    public function down(): void
    {
        Schema::table('customer_prospects', function (Blueprint $table) {
            $table->dropColumn([
                'gender', 'religion', 'marital_status', 'occupation', 'blood_type', 'nationality',
                'street_id', 'house_number', 'rt', 'rw',
                'latitude', 'longitude', 'location_source', 'location_accuracy',
                'ktp_file_type', 'installation_fee_breakdown',
            ]);
        });

        Schema::table('survey_reports', function (Blueprint $table) {
            $table->dropColumn([
                'land_status', 'surveyor_notes', 'location_source', 'location_accuracy',
            ]);
        });
    }
};
