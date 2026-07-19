<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('asset_type', 30);
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('frequency', 20);
            $table->integer('interval_value')->nullable();
            $table->decimal('meter_hour_target', 12, 2)->nullable();
            $table->date('next_due_date')->nullable();
            $table->date('last_completed_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('checklist_json')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('schedule_id');
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->date('execution_date');
            $table->unsignedBigInteger('technician_id')->nullable();
            $table->text('findings')->nullable();
            $table->decimal('cost_labor', 15, 2)->default(0);
            $table->decimal('cost_material', 15, 2)->default(0);
            $table->string('outcome', 20)->default('completed');
            $table->text('recommendations')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
        Schema::dropIfExists('maintenance_schedules');
    }
};
