<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('doc_number', 30)->unique();
            $table->string('title', 200);
            $table->string('category', 30);
            $table->integer('version')->default(1);
            $table->string('file_path');
            $table->string('file_type', 10);
            $table->bigInteger('file_size');
            $table->json('tags')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('uploaded_by');
            $table->date('retention_until')->nullable();
            $table->timestamps();
        });

        Schema::create('document_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('approver_id');
            $table->integer('approval_order');
            $table->string('status', 20)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->text('comments')->nullable();
            $table->string('signature_data')->nullable();
            $table->timestamps();
        });

        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('call_id', 50)->nullable();
            $table->string('direction', 10);
            $table->string('caller_number', 20)->nullable();
            $table->string('callee_number', 20)->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('complaint_id')->nullable();
            $table->string('disposition', 30)->nullable();
            $table->text('notes')->nullable();
            $table->string('recording_url')->nullable();
            $table->string('status', 20)->default('completed');
            $table->timestamps();
        });

        Schema::create('gis_features', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('feature_type', 30);
            $table->string('name', 200)->nullable();
            $table->json('geometry');
            $table->json('properties')->nullable();
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('name', 50);
            $table->string('provider', 30);
            $table->json('credentials')->nullable();
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('integration_id');
            $table->string('action', 50);
            $table->string('status', 20);
            $table->integer('http_status')->nullable();
            $table->text('request_payload')->nullable();
            $table->text('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('name', 50);
            $table->string('key', 64)->unique();
            $table->json('scopes')->nullable();
            $table->integer('rate_limit')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('last_used_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('integrations');
        Schema::dropIfExists('gis_features');
        Schema::dropIfExists('call_logs');
        Schema::dropIfExists('document_approvals');
        Schema::dropIfExists('documents');
    }
};
