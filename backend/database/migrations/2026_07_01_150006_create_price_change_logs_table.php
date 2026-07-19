<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * price_change_logs — audit perubahan harga modul (PRD 4.D.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_change_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module_code');
            $table->decimal('old_price', 15, 2)->nullable();
            $table->decimal('new_price', 15, 2)->nullable();
            $table->unsignedBigInteger('changed_by')->nullable(); // platform_admin id
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_change_logs');
    }
};
