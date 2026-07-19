<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * module_price_tiers — harga per skala jumlah pelanggan (PRD 4.D.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('module_code');
            $table->unsignedInteger('min_customers')->default(0);
            $table->unsignedInteger('max_customers')->nullable(); // null = tak terbatas
            $table->decimal('price_year', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('module_code')->references('code')->on('modules')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_price_tiers');
    }
};
