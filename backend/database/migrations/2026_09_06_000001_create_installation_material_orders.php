<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order material pemasangan (temuan2.md M-10 → implementasi reservasi +
 * stock-out + jurnal). Satu prospek boleh punya riwayat order, tetapi hanya
 * satu order aktif (reserved/issued) — ditegakkan lewat lookup service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_material_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->index();
            $table->foreignId('prospect_id')->constrained('customer_prospects')->cascadeOnDelete();
            $table->unsignedBigInteger('warehouse_id')->index();
            $table->string('status', 20)->default('reserved'); // reserved|issued|cancelled
            $table->json('items'); // [{material_id, material_code, name, unit, qty, unit_cost, line_cost}]
            $table->decimal('total_cost', 16, 2)->default(0);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['pdam_org_id', 'prospect_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_material_orders');
    }
};
