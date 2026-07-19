<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * subscription_modules — INTI entitlement modul per tenant (PRD 4.D.2 & 16.1).
 * Gerbang lock/unlock 2 jalur: gateway (self-service) ATAU manual super-admin (cash).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdam_org_id')->constrained('pdam_organizations')->cascadeOnDelete();
            $table->string('module_code');
            $table->string('status')->default('locked'); // locked|active|expired|trial
            $table->string('activation_method')->nullable(); // gateway|cash|manual_transfer|free_trial|promo|free_default
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('payment_proof_url')->nullable();
            $table->unsignedBigInteger('activated_by')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['pdam_org_id', 'module_code']);
            $table->foreign('module_code')->references('code')->on('modules')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_modules');
    }
};
