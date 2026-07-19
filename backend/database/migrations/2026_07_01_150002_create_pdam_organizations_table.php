<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pdam_organizations — TENANT. Akar isolasi multi-tenant (PRD 5 & 16.1).
 * Setiap tabel bisnis mereferensikan pdam_org_id ke tabel ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdam_organizations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // slug unik PDAM, mis. "pdam-pontianak"
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('logo_url')->nullable();
            $table->json('letterhead_config')->nullable(); // konfigurasi kop surat
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('timezone')->default('Asia/Jakarta');
            $table->string('subscription_status')->default('active'); // active|suspended|trial
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdam_organizations');
    }
};
