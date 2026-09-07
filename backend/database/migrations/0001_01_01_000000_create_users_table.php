<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Multi-tenant: setiap user milik satu PDAM (PRD 16.2)
            // FK ditambahkan di migration terpisah (pdam_organizations dibuat setelah tabel ini)
            $table->unsignedBigInteger('pdam_org_id')->nullable()->index();

            $table->string('name');                       // full_name
            $table->string('email');                      // unik per tenant (lihat unique di bawah)
            $table->string('phone')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_tenant_admin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('fcm_token')->nullable();
            $table->rememberToken();
            $table->timestamps();

            // Email unik dalam satu tenant (bukan global) — beda PDAM boleh email sama
            $table->unique(['pdam_org_id', 'email']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
