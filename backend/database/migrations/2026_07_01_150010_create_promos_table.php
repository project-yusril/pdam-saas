<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * promos + promo_targets + promo_redemptions — promo/diskon musiman (PRD 4.E.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('discount_type');            // percent|fixed
            $table->decimal('discount_value', 15, 2);
            $table->string('scope_type')->default('all'); // all|tier|modules|tenant
            $table->json('scope_value')->nullable();
            $table->string('target_type')->default('all_tenants'); // all_tenants|specific
            $table->decimal('max_discount_cap', 15, 2)->nullable();
            $table->unsignedInteger('usage_quota')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status')->default('scheduled'); // scheduled|active|expired|disabled
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('promo_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_id')->constrained('promos')->cascadeOnDelete();
            $table->foreignId('pdam_org_id')->constrained('pdam_organizations')->cascadeOnDelete();
        });

        Schema::create('promo_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_id')->constrained('promos')->cascadeOnDelete();
            $table->foreignId('pdam_org_id')->constrained('pdam_organizations')->cascadeOnDelete();
            $table->string('module_code');
            $table->decimal('original_price', 15, 2);
            $table->decimal('discount_amount', 15, 2);
            $table->decimal('final_price', 15, 2);
            $table->timestamp('redeemed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_redemptions');
        Schema::dropIfExists('promo_targets');
        Schema::dropIfExists('promos');
    }
};
