<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gis_network_edges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('pipe_feature_id');
            $table->unsignedBigInteger('from_node_id');
            $table->string('from_node_type', 30);
            $table->unsignedBigInteger('to_node_id');
            $table->string('to_node_type', 30);
            $table->decimal('length_meters', 10, 2)->nullable();
            $table->timestamps();
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gis_network_edges');
    }
};
