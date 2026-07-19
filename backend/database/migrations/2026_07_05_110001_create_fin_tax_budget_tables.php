<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('tax_type', 20);
            $table->string('reference_type', 30)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->date('tax_date');
            $table->decimal('dpp', 15, 2);
            $table->decimal('tax_amount', 15, 2);
            $table->string('status', 20)->default('unpaid');
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations');
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('name');
            $table->year('fiscal_year');
            $table->decimal('total_amount', 15, 2);
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations');
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('budget_id');
            $table->string('account_code', 20);
            $table->string('project_code', 30)->nullable();
            $table->string('cost_center_code', 20)->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('realized', 15, 2)->default(0);
            $table->timestamps();
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations');
            $table->foreign('budget_id')->references('id')->on('budgets');
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('status', 20)->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('budget', 15, 2)->default(0);
            $table->timestamps();
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations');
        });

        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations');
        });

        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('name');
            $table->string('frequency', 10);
            $table->decimal('amount', 15, 2);
            $table->json('journal_lines');
            $table->date('next_run_date');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('last_entry_id')->nullable();
            $table->timestamps();
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_transactions');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('tax_records');
    }
};
