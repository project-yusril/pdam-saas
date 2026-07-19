<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1.5 — Tagihan + rincian komponen.
 * bills: header tagihan bulanan. bill_items: breakdown (tier 1/2/3, abonemen,
 * admin, pemeliharaan meter, denda) agar transparan & auditable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('bill_number', 40);
            $table->string('period', 7); // YYYY-MM

            $table->unsignedBigInteger('previous_reading_id')->nullable();
            $table->unsignedBigInteger('current_reading_id')->nullable();
            $table->unsignedInteger('previous_reading')->default(0);
            $table->unsignedInteger('current_reading')->default(0);
            $table->unsignedInteger('consumption')->default(0); // m³

            $table->decimal('water_charge', 14, 2)->default(0);   // total tiered
            $table->decimal('abonemen', 12, 2)->default(0);
            $table->decimal('meter_maintenance_fee', 12, 2)->default(0);
            $table->decimal('admin_fee', 12, 2)->default(0);
            $table->decimal('penalty', 12, 2)->default(0);
            $table->decimal('amount_due', 16, 2)->default(0);     // grand total

            $table->string('status')->default('unpaid'); // unpaid|paid|overdue|waived
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();

            $table->unique(['pdam_org_id', 'customer_id', 'period']);
            $table->index(['pdam_org_id', 'status']);
        });

        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('bill_id')->constrained('bills')->cascadeOnDelete();
            $table->string('component');   // tier_1|tier_2|tier_3|abonemen|admin|meter_maintenance|penalty
            $table->string('label');
            $table->unsignedInteger('quantity')->default(0); // m³ untuk tier
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_items');
        Schema::dropIfExists('bills');
    }
};
