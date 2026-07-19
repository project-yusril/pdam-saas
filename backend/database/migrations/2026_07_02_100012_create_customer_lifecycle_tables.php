<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2.6 — Lifecycle pelanggan: pemutusan, penyambungan, balik nama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disconnections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('type')->default('isolir'); // isolir|terminated|temporary_closed
            $table->string('reason')->nullable();
            $table->date('effective_date')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamps();
        });

        Schema::create('reconnections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->unsignedBigInteger('disconnection_id')->nullable();
            $table->decimal('fee', 16, 2)->default(0); // biaya buka isolir
            $table->date('effective_date')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamps();
        });

        Schema::create('ownership_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('old_owner_name');
            $table->string('new_owner_name');
            $table->text('new_owner_nik')->nullable(); // ENCRYPTED
            $table->string('new_owner_phone', 30)->nullable();
            $table->date('effective_date')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ownership_transfers');
        Schema::dropIfExists('reconnections');
        Schema::dropIfExists('disconnections');
    }
};
