<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * modules — katalog 26 modul (PRD 4.C). Data platform (bukan per tenant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // CORE, WH, MTR, ...
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('tier')->default(1);   // 1|2|3
            $table->decimal('base_price_year', 15, 2)->default(0);
            $table->json('dependencies')->nullable();  // ["CORE"] dst
            $table->boolean('is_default')->default(false); // true = paket dasar gratis (CORE)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
