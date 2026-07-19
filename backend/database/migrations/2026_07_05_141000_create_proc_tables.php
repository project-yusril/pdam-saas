<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('code', 20)->unique();
            $table->string('name', 200);
            $table->string('npwp', 30)->nullable();
            $table->string('contact_name', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('category', 50)->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->boolean('is_blacklisted')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tenders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('tender_number', 30)->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('category', 50)->nullable();
            $table->decimal('budget_ceiling', 15, 2);
            $table->date('publish_date');
            $table->date('submission_deadline');
            $table->date('award_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('winner_vendor_id')->nullable();
            $table->decimal('final_price', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('tender_bids', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('tender_id');
            $table->unsignedBigInteger('vendor_id');
            $table->decimal('bid_price', 15, 2);
            $table->text('technical_proposal')->nullable();
            $table->decimal('technical_score', 5, 2)->nullable();
            $table->decimal('price_score', 5, 2)->nullable();
            $table->decimal('total_score', 5, 2)->nullable();
            $table->integer('rank')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('vendor_id');
            $table->string('contract_number', 30)->unique();
            $table->string('title', 200);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('value', 15, 2);
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('tender_id')->nullable();
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_evaluations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->decimal('quality_score', 3, 1)->nullable();
            $table->decimal('delivery_score', 3, 1)->nullable();
            $table->decimal('price_score', 3, 1)->nullable();
            $table->decimal('compliance_score', 3, 1)->nullable();
            $table->decimal('overall_score', 3, 1)->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('pr_number', 30)->unique();
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->string('status', 20)->default('draft');
            $table->date('required_date')->nullable();
            $table->decimal('estimated_total', 15, 2)->default(0);
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('vendor_evaluations');
        Schema::dropIfExists('vendor_contracts');
        Schema::dropIfExists('tender_bids');
        Schema::dropIfExists('tenders');
        Schema::dropIfExists('vendors');
    }
};
