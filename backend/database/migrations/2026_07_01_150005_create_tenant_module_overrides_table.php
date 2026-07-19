<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tenant_module_overrides — harga khusus per tenant (PRD 4.D.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_module_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdam_org_id')->constrained('pdam_organizations')->cascadeOnDelete();
            $table->string('module_code');
            $table->decimal('custom_price', 15, 2);
            $table->string('note')->nullable();
            $table->unsignedBigInteger('set_by')->nullable(); // platform_admin id
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->foreign('module_code')->references('code')->on('modules')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_module_overrides');
    }
};
