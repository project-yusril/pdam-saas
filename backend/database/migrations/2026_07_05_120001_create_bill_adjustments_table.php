<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('bill_id');
            $table->string('type', 20);
            $table->string('reason');
            $table->decimal('old_amount', 15, 2);
            $table->decimal('new_amount', 15, 2);
            $table->decimal('adjustment_amount', 15, 2);
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations');
            $table->foreign('bill_id')->references('id')->on('bills');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_adjustments');
    }
};
