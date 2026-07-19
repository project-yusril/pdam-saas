<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_number')->unique();
            $table->foreignId('pdam_org_id')->constrained('pdam_organizations')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 100);
            $table->string('status')->default('pending');
            $table->decimal('amount', 15, 2);
            $table->string('gateway')->default('midtrans');
            $table->string('gateway_transaction_id')->nullable();
            $table->string('payment_url')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->unique(['pdam_org_id', 'idempotency_key']);
        });

        Schema::create('saas_purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saas_purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('module_code');
            $table->string('module_name');
            $table->decimal('amount', 15, 2);
            $table->json('dependencies');
            $table->timestamps();
            $table->unique(['saas_purchase_order_id', 'module_code'], 'spo_lines_order_module_uq');
            $table->foreign('module_code')->references('code')->on('modules')->restrictOnDelete();
        });

        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdam_org_id')->constrained('pdam_organizations')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('dataset', 50);
            $table->string('format', 10);
            $table->json('columns');
            $table->json('filters')->nullable();
            $table->string('sort')->nullable();
            $table->string('frequency', 10);
            $table->string('timezone', 64);
            $table->string('local_time', 5);
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('scheduled_report_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdam_org_id')->constrained('pdam_organizations')->cascadeOnDelete();
            $table->foreignId('scheduled_report_id')->constrained()->cascadeOnDelete();
            $table->timestamp('scheduled_for');
            $table->string('status')->default('queued');
            $table->string('artifact_path')->nullable();
            $table->string('filename')->nullable();
            $table->string('content_type')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['scheduled_report_id', 'scheduled_for'], 'sr_runs_report_slot_uq');
            $table->index(['pdam_org_id', 'scheduled_report_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_report_runs');
        Schema::dropIfExists('scheduled_reports');
        Schema::dropIfExists('saas_purchase_order_lines');
        Schema::dropIfExists('saas_purchase_orders');
    }
};
