<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chemicals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('code', 20);
            $table->string('name', 100);
            $table->string('unit', 20);
            $table->decimal('standard_dosage', 10, 4)->nullable();
            $table->decimal('safety_threshold', 10, 4)->nullable();
            $table->text('msds_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('chemical_suppliers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('name', 100);
            $table->string('contact_name', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('chemical_purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('pr_number', 30)->unique();
            $table->unsignedBigInteger('chemical_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->decimal('quantity', 12, 4);
            $table->string('unit', 20);
            $table->date('required_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->timestamps();
        });

        Schema::create('chemical_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('receipt_number', 30)->unique();
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->date('receipt_date');
            $table->string('batch_number', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status', 20)->default('pending_qc');
            $table->decimal('total_cost', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('chemical_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('chemical_receipt_id');
            $table->unsignedBigInteger('chemical_id');
            $table->decimal('quantity', 12, 4);
            $table->string('unit', 20);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('chemical_qc_tests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('chemical_receipt_id');
            $table->string('test_name', 100);
            $table->decimal('result_value', 10, 4)->nullable();
            $table->string('result_unit', 20)->nullable();
            $table->string('verdict', 20);
            $table->unsignedBigInteger('tested_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('chemical_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('chemical_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->string('batch_number', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity', 12, 4);
            $table->string('unit', 20);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('chemical_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('chemical_stock_id');
            $table->string('type', 10);
            $table->decimal('quantity', 12, 4);
            $table->decimal('balance_after', 12, 4);
            $table->string('reference_type', 30)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('chemical_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('chemical_id');
            $table->date('usage_date');
            $table->decimal('quantity', 12, 4);
            $table->string('unit', 20);
            $table->decimal('water_produced_m3', 12, 2)->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
        });

        Schema::create('chemical_forecasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('chemical_id');
            $table->string('forecast_period', 7);
            $table->decimal('forecasted_quantity', 12, 4);
            $table->decimal('confidence_low', 12, 4)->nullable();
            $table->decimal('confidence_high', 12, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('chemical_stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('chemical_stock_id');
            $table->date('opname_date');
            $table->decimal('system_quantity', 12, 4);
            $table->decimal('actual_quantity', 12, 4);
            $table->decimal('difference', 12, 4);
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('checked_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chemical_stock_opnames');
        Schema::dropIfExists('chemical_forecasts');
        Schema::dropIfExists('chemical_usages');
        Schema::dropIfExists('chemical_transactions');
        Schema::dropIfExists('chemical_stocks');
        Schema::dropIfExists('chemical_qc_tests');
        Schema::dropIfExists('chemical_receipt_items');
        Schema::dropIfExists('chemical_receipts');
        Schema::dropIfExists('chemical_purchase_requests');
        Schema::dropIfExists('chemical_suppliers');
        Schema::dropIfExists('chemicals');
    }
};
