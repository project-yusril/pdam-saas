<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * saas_invoices — tagihan langganan SaaS ke tenant (PRD 16.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdam_org_id')->constrained('pdam_organizations')->cascadeOnDelete();
            $table->string('period'); // YYYY-MM atau YYYY
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->default('unpaid'); // unpaid|paid|overdue
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_invoices');
    }
};
