<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payment_refunds — catatan pengembalian pembayaran (fitur PRD §23 "refund",
 * di-gate `business.refund.enabled`). Setiap refund membuat jurnal reversal
 * (DEBIT/KREDIT tertukar dari jurnal pembayaran asal) lalu menandainya.
 * Idempoten per-payment via `payment_id`+unique(partial) dicek di service
 * (kolom hanya menyimpan total agar bisa multi-partial).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->unsignedBigInteger('payment_id')->index();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('amount', 16, 2);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('approved_by');
            $table->string('method')->default('counter'); // counter|transfer|cash
            $table->boolean('reverses_gateway')->default(false);
            $table->string('reason')->nullable();
            $table->string('status')->default('completed'); // completed|reversed_gateway
            $table->timestamps();
            $table->index(['pdam_org_id', 'payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
