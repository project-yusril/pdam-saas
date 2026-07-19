<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1.9 — Notifikasi in-app (dan channel email/push tercatat).
 * Dipakai untuk pengingat tagihan, eskalasi, dsb.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index(); // penerima
            $table->string('type');    // bill_reminder|overdue|escalation|info
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();  // payload tambahan (bill_id, dll)
            $table->string('channel')->default('in_app'); // in_app|email|push
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
