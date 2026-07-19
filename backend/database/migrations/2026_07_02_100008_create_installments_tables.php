<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1.8 — Cicilan tunggakan (PRD 10.4).
 * installment_plans: rencana cicilan (approval berjenjang).
 * installment_plan_bills: tagihan tunggakan yang dicicil.
 * installment_schedules: jadwal termin + status bayar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('plan_number', 40);
            $table->decimal('total_amount', 16, 2);
            $table->unsignedInteger('tenor_months');
            $table->decimal('monthly_amount', 16, 2);
            $table->string('status')->default('draft');
            // draft|pending_approval|approved|rejected|active|completed
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('director_approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('director_approved_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['pdam_org_id', 'plan_number']);
        });

        Schema::create('installment_plan_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('plan_id')->constrained('installment_plans')->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained('bills');
            $table->decimal('amount', 16, 2);
            $table->timestamps();
        });

        Schema::create('installment_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('plan_id')->constrained('installment_plans')->cascadeOnDelete();
            $table->unsignedInteger('installment_no');
            $table->decimal('amount', 16, 2);
            $table->date('due_date');
            $table->string('status')->default('unpaid'); // unpaid|paid|overdue
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'installment_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_schedules');
        Schema::dropIfExists('installment_plan_bills');
        Schema::dropIfExists('installment_plans');
    }
};
