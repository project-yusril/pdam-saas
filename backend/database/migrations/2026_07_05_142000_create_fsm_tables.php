<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('wo_number', 30)->unique();
            $table->string('type', 30);
            $table->string('priority', 15)->default('medium');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('source_type', 30)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status', 20)->default('open');
            $table->dateTime('sla_due_at')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('description')->nullable();
            $table->json('photos_before')->nullable();
            $table->json('photos_after')->nullable();
            $table->text('resolution')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->boolean('customer_signature')->default(false);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();
        });

        Schema::create('work_order_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('work_order_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('action', 30);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('technician_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_locations');
        Schema::dropIfExists('work_order_logs');
        Schema::dropIfExists('work_orders');
    }
};
