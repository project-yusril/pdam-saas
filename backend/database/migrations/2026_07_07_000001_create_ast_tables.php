<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 7 — AST (Aset Tetap & Penyusutan).
 * asset_categories (klasifikasi + default masa manfaat/metode + akun COA) +
 * fixed_assets (register aset) + depreciation_entries (penyusutan bulanan) +
 * asset_movements (mutasi lokasi) + asset_disposals (pelepasan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdam_org_id')->constrained('pdam_organizations')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            // Masa manfaat default (bulan) + metode default.
            $table->unsignedInteger('useful_life_months')->default(60);
            $table->enum('depreciation_method', ['straight_line', 'declining_balance'])->default('straight_line');
            // Tarif saldo menurun (% per tahun) — dipakai bila declining_balance.
            $table->decimal('declining_rate', 5, 2)->default(0);
            // Tanah tidak disusutkan.
            $table->boolean('is_depreciable')->default(true);
            // Akun COA: aset, akumulasi penyusutan, beban penyusutan.
            $table->string('asset_account_code')->nullable();
            $table->string('accumulation_account_code')->nullable();
            $table->string('expense_account_code')->nullable();
            $table->timestamps();

            $table->unique(['pdam_org_id', 'code']);
        });

        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdam_org_id')->constrained('pdam_organizations')->cascadeOnDelete();
            $table->foreignId('asset_category_id')->constrained('asset_categories')->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->string('code')->comment('kode aset otomatis');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('location_note')->nullable();
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 18, 2);
            $table->decimal('residual_value', 18, 2)->default(0);
            $table->unsignedInteger('useful_life_months');
            $table->enum('depreciation_method', ['straight_line', 'declining_balance'])->default('straight_line');
            $table->decimal('declining_rate', 5, 2)->default(0);
            $table->boolean('is_depreciable')->default(true);
            // Akumulasi penyusutan berjalan + nilai buku (dicache untuk laporan cepat).
            $table->decimal('accumulated_depreciation', 18, 2)->default(0);
            $table->decimal('book_value', 18, 2);
            $table->enum('source', ['beli', 'hibah', 'kapitalisasi'])->default('beli');
            $table->enum('status', ['aktif', 'dijual', 'dihapus', 'rusak'])->default('aktif');
            $table->string('document_number')->nullable();
            $table->string('photo_url')->nullable();
            // Periode terakhir disusutkan (YYYY-MM) untuk cegah dobel.
            $table->string('last_depreciated_period', 7)->nullable();
            $table->timestamps();

            $table->unique(['pdam_org_id', 'code']);
            $table->index(['pdam_org_id', 'status']);
        });

        Schema::create('depreciation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdam_org_id')->constrained('pdam_organizations')->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->string('period', 7)->comment('YYYY-MM');
            $table->decimal('depreciation_amount', 18, 2);
            $table->decimal('accumulated_after', 18, 2);
            $table->decimal('book_value_after', 18, 2);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            // Idempoten: 1 aset hanya 1 entri per periode.
            $table->unique(['fixed_asset_id', 'period']);
        });

        Schema::create('asset_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdam_org_id')->constrained('pdam_organizations')->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->foreignId('from_zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignId('to_zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->string('from_location')->nullable();
            $table->string('to_location')->nullable();
            $table->date('moved_at');
            $table->string('reason')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('asset_disposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdam_org_id')->constrained('pdam_organizations')->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->enum('disposal_type', ['dijual', 'dihapus', 'rusak']);
            $table->date('disposal_date');
            $table->decimal('sale_value', 18, 2)->default(0);
            $table->decimal('book_value_at_disposal', 18, 2);
            $table->decimal('gain_loss', 18, 2)->default(0)->comment('positif=laba, negatif=rugi');
            $table->string('reason')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_disposals');
        Schema::dropIfExists('asset_movements');
        Schema::dropIfExists('depreciation_entries');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('asset_categories');
    }
};
