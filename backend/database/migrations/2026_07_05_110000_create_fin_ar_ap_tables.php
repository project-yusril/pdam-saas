<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('invoice_number', 50)->unique();
            $table->string('reference_type', 30)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('status', 20)->default('unpaid');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations');
        });

        Schema::create('sales_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('sales_invoice_id');
            $table->string('description')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->string('account_code', 20)->nullable();
            $table->timestamps();
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations');
            $table->foreign('sales_invoice_id')->references('id')->on('sales_invoices');
        });

        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->string('invoice_number', 50)->unique();
            $table->string('supplier_invoice_number', 100)->nullable();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('status', 20)->default('unpaid');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations');
        });

        Schema::create('purchase_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('purchase_invoice_id');
            $table->string('description')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->string('account_code', 20)->nullable();
            $table->timestamps();
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations');
            $table->foreign('purchase_invoice_id')->references('id')->on('purchase_invoices');
        });

        Schema::create('ar_ap_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('payment_type', 10); // in | out
            $table->string('payable_type')->nullable();
            $table->unsignedBigInteger('payable_id')->nullable();
            $table->string('payment_number', 50)->unique();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 30);
            $table->string('bank_account', 100)->nullable();
            $table->date('payment_date');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ar_ap_payments');
        Schema::dropIfExists('purchase_invoice_items');
        Schema::dropIfExists('purchase_invoices');
        Schema::dropIfExists('sales_invoice_items');
        Schema::dropIfExists('sales_invoices');
    }
};
