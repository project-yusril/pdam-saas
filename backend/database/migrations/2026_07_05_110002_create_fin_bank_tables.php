<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('code', 20);
            $table->string('account_name');
            $table->string('account_number', 30);
            $table->string('bank_name', 50);
            $table->string('currency', 5)->default('IDR');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->string('coa_account_code', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations');
        });

        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('bank_account_id');
            $table->date('statement_date');
            $table->date('reconciliation_date');
            $table->decimal('statement_balance', 15, 2);
            $table->decimal('book_balance', 15, 2);
            $table->decimal('difference', 15, 2);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations');
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts');
        });

        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('code', 5);
            $table->string('name', 30);
            $table->string('symbol', 5);
            $table->integer('decimal_places')->default(2);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations');
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('from_currency', 5);
            $table->string('to_currency', 5)->default('IDR');
            $table->decimal('rate', 18, 6);
            $table->date('effective_date');
            $table->timestamps();
            $table->foreign('pdam_org_id')->references('id')->on('pdam_organizations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('bank_accounts');
    }
};
