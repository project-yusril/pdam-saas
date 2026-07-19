<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4 — WH + PROC (Gudang & Pengadaan).
 * materials, suppliers, material_stocks (per gudang), material_transactions (log),
 * purchase_orders (multi-level approval), stock_transfers (+ items), stock_adjustments,
 * repair_orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->string('code', 30);
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['pdam_org_id', 'code']);
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->string('code', 40);
            $table->string('name');
            $table->string('category')->nullable(); // pipa|sambungan|valve|meteran|sipil|bantu
            $table->string('unit', 20)->default('pcs'); // meter|pcs|sak|kg
            $table->decimal('last_price', 16, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['pdam_org_id', 'code']);
        });

        Schema::create('material_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->decimal('current_stock', 16, 2)->default(0);
            $table->decimal('minimum_stock', 16, 2)->default(0);
            $table->timestamps();

            $table->unique(['material_id', 'warehouse_id']);
        });

        Schema::create('material_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('material_id')->constrained('materials');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('transaction_type'); // stock_in|stock_out
            $table->decimal('quantity', 16, 2);
            $table->decimal('balance_after', 16, 2)->default(0);
            $table->string('reference_type')->nullable();
            // purchase_order|stock_transfer|installation|repair|adjustment
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['material_id', 'warehouse_id']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->string('po_number', 40);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->json('items'); // [{material_id, qty, price}]
            $table->decimal('total_estimated_price', 18, 2)->default(0);
            $table->string('urgency')->default('normal'); // normal|urgent
            $table->string('status')->default('draft');
            // draft|tech_approved|dir_approved|fin_approved|purchased|received|completed|rejected
            $table->unsignedBigInteger('tech_approved_by')->nullable();
            $table->timestamp('tech_approved_at')->nullable();
            $table->unsignedBigInteger('dir_approved_by')->nullable();
            $table->timestamp('dir_approved_at')->nullable();
            $table->unsignedBigInteger('fin_approved_by')->nullable();
            $table->timestamp('fin_approved_at')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['pdam_org_id', 'po_number']);
            $table->index(['pdam_org_id', 'status']);
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->string('transfer_number', 40);
            $table->string('transfer_type'); // main_to_buffer|buffer_to_buffer
            $table->foreignId('from_warehouse_id')->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->constrained('warehouses');
            $table->string('reason')->nullable();
            // urgent_installation|stock_imbalance|closer_location|other
            $table->string('status')->default('draft');
            // draft|approved|in_transit|received|completed
            $table->unsignedBigInteger('reference_order_id')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->string('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['pdam_org_id', 'transfer_number']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials');
            $table->decimal('quantity_requested', 16, 2);
            $table->decimal('quantity_received', 16, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('material_id')->constrained('materials');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->decimal('system_stock', 16, 2);
            $table->decimal('physical_stock', 16, 2);
            $table->decimal('difference', 16, 2);
            $table->string('reason')->nullable(); // opname|damage|loss
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('adjusted_by')->nullable();
            $table->timestamps();
        });

        Schema::create('repair_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->string('order_number', 40);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('description');
            $table->json('materials_used')->nullable(); // [{material_id, qty}]
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('status')->default('open'); // open|in_progress|completed
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['pdam_org_id', 'order_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_orders');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('material_transactions');
        Schema::dropIfExists('material_stocks');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('suppliers');
    }
};
