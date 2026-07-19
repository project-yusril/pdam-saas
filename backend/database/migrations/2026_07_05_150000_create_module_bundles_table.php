<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->decimal('price_year', 15, 2)->default(0);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('module_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('module_bundle_id');
            $table->unsignedBigInteger('module_id');
            $table->timestamps();
            $table->foreign('module_bundle_id')->references('id')->on('module_bundles');
            $table->foreign('module_id')->references('id')->on('modules');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_bundle_items');
        Schema::dropIfExists('module_bundles');
    }
};
