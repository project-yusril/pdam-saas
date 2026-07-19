<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1.6 — Akuntansi double-entry (PRD 3.4).
 * chart_of_accounts, journal_entries (header), journal_entry_lines (detail),
 * accounting_periods (lock periode). Aturan: SUM(DEBIT)=SUM(KREDIT) per jurnal
 * ditegakkan di JournalService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->string('code', 20);   // 1-001, 4-001
            $table->string('name');
            $table->string('type');       // ASSET|LIABILITY|EQUITY|REVENUE|EXPENSE
            $table->string('normal_balance'); // DEBIT|KREDIT
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['pdam_org_id', 'code']);
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->string('period', 7);  // YYYY-MM
            $table->string('status')->default('open'); // open|closed
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamps();

            $table->unique(['pdam_org_id', 'period']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->string('entry_number', 40)->nullable();
            $table->date('entry_date');
            $table->string('period', 7)->index(); // YYYY-MM
            $table->string('description');
            $table->string('reference_type')->nullable();
            // BILL|PAYMENT|INSTALLATION_FEE|PURCHASE_ORDER|STOCK_ADJUSTMENT|MANUAL
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('created_by')->default('system'); // "system" jika otomatis
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('journal_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->string('type');           // DEBIT|KREDIT
            $table->decimal('amount', 18, 2);
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('chart_of_accounts');
    }
};
