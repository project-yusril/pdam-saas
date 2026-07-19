<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_readings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('meter_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('value', 12, 4);
            $table->decimal('flow_rate', 12, 4)->nullable();
            $table->decimal('pressure', 10, 4)->nullable();
            $table->decimal('battery', 5, 2)->nullable();
            $table->decimal('signal_strength', 5, 2)->nullable();
            $table->dateTime('reading_at');
            $table->string('source', 20)->default('lorawan');
            $table->string('device_id', 50)->nullable();
            $table->boolean('validated')->default(false);
            $table->timestamps();
        });

        Schema::create('production_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->date('production_date');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('raw_water_m3', 12, 2)->nullable();
            $table->decimal('treated_water_m3', 12, 2)->nullable();
            $table->decimal('distributed_water_m3', 12, 2)->nullable();
            $table->decimal('pump_runtime_hours', 8, 2)->nullable();
            $table->decimal('power_consumption_kwh', 10, 2)->nullable();
            $table->decimal('turbidity_ntu', 8, 2)->nullable();
            $table->decimal('ph', 4, 2)->nullable();
            $table->decimal('chlorine_residual', 8, 4)->nullable();
            $table->string('status', 20)->default('normal');
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
        });

        Schema::create('dma_zones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->json('boundary')->nullable();
            $table->decimal('total_connections', 8, 0)->nullable();
            $table->decimal('base_demand_m3day', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('distribution_readings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('dma_zone_id');
            $table->dateTime('reading_at');
            $table->decimal('flow_rate_m3h', 10, 2)->nullable();
            $table->decimal('pressure_bar', 8, 2)->nullable();
            $table->decimal('reservoir_level_percent', 5, 2)->nullable();
            $table->decimal('chlorine_residual', 8, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('nrw_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('dma_zone_id');
            $table->string('period', 7);
            $table->decimal('system_input_m3', 12, 2);
            $table->decimal('billed_metered_m3', 12, 2);
            $table->decimal('unbilled_metered_m3', 12, 2)->default(0);
            $table->decimal('unbilled_unmetered_m3', 12, 2)->default(0);
            $table->decimal('authorised_consumption_m3', 12, 2);
            $table->decimal('water_losses_m3', 12, 2);
            $table->decimal('apparent_losses_m3', 12, 2)->nullable();
            $table->decimal('real_losses_m3', 12, 2)->nullable();
            $table->decimal('nrw_percentage', 8, 2)->nullable();
            $table->decimal('ili', 8, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('ml_predictions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('model_name', 50);
            $table->string('prediction_type', 30);
            $table->string('entity_type', 30)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('period', 7)->nullable();
            $table->decimal('predicted_value', 12, 4);
            $table->decimal('confidence_min', 12, 4)->nullable();
            $table->decimal('confidence_max', 12, 4)->nullable();
            $table->decimal('actual_value', 12, 4)->nullable();
            $table->json('features')->nullable();
            $table->string('status', 20)->default('predicted');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_predictions');
        Schema::dropIfExists('nrw_balances');
        Schema::dropIfExists('distribution_readings');
        Schema::dropIfExists('dma_zones');
        Schema::dropIfExists('production_logs');
        Schema::dropIfExists('sensor_readings');
    }
};
