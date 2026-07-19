<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('ticket_number', 30)->unique();
            $table->string('category', 30);
            $table->string('priority', 15)->default('medium');
            $table->string('subject');
            $table->text('description');
            $table->json('attachments')->nullable();
            $table->string('status', 20)->default('open');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->dateTime('sla_due_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->text('resolution')->nullable();
            $table->unsignedBigInteger('repair_order_id')->nullable();
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->timestamps();
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations');
        });

        Schema::create('complaint_tracks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('complaint_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('action', 30);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations');
            $table->foreign('complaint_id')->references('id')->on('complaints');
        });

        Schema::create('customer_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('complaint_id')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_feedbacks');
        Schema::dropIfExists('complaint_tracks');
        Schema::dropIfExists('complaints');
    }
};
