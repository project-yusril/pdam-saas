<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1.7 — Pembayaran + log gateway.
 * payments: transaksi bayar (tagihan bulanan / biaya pemasangan / cicilan).
 * payment_gateway_logs: raw payload webhook untuk audit & idempotency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->string('payment_type');   // monthly_bill|installation_fee|installment
            $table->unsignedBigInteger('bill_id')->nullable();
            $table->unsignedBigInteger('prospect_id')->nullable();       // biaya pemasangan
            $table->unsignedBigInteger('installment_schedule_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();

            $table->string('payment_number', 40);
            $table->decimal('amount', 16, 2);
            $table->string('payment_method')->nullable(); // qris|va|gopay|cash|...
            $table->string('channel')->default('gateway'); // gateway|counter (loket)
            $table->string('midtrans_order_id')->nullable();
            $table->string('midtrans_transaction_id')->nullable();
            $table->string('status')->default('pending'); // pending|success|failed|expired
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->unsignedBigInteger('received_by')->nullable(); // kasir (loket)
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();

            $table->unique(['pdam_org_id', 'payment_number']);
            // idempotency: satu transaksi gateway hanya tercatat sekali
            $table->unique('midtrans_transaction_id');
            $table->index(['pdam_org_id', 'status']);
        });

        Schema::create('payment_gateway_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->nullable()->index();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('event')->nullable();       // notification|charge|status
            $table->string('order_id')->nullable();
            $table->string('transaction_status')->nullable();
            $table->json('payload')->nullable();
            $table->string('signature')->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_logs');
        Schema::dropIfExists('payments');
    }
};
