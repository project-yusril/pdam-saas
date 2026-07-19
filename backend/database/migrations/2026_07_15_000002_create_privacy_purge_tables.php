<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_purge_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('target', 40)->default('activity_logs');
            $table->string('policy_version', 20);
            $table->text('reason');
            $table->timestamp('cutoff_at');
            $table->unsignedInteger('candidate_count');
            $table->unsignedInteger('deleted_count')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['pdam_org_id', 'status']);
            $table->foreign('requested_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users');
        });

        Schema::create('privacy_audit_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->unsignedBigInteger('privacy_purge_request_id');
            $table->unsignedBigInteger('actor_id');
            $table->string('event_type', 40);
            $table->json('payload');
            $table->char('previous_hash', 64)->nullable();
            $table->char('event_hash', 64)->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('privacy_purge_request_id')->references('id')->on('privacy_purge_requests');
            $table->foreign('actor_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_audit_events');
        Schema::dropIfExists('privacy_purge_requests');
    }
};
