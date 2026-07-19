<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_NAME = 'module_bundle_items_bundle_module_unique';

    private const BUNDLE_INDEX_NAME = 'module_bundle_items_bundle_id_index';

    public function up(): void
    {
        DB::table('module_bundle_items')
            ->select('module_bundle_id', 'module_id')
            ->groupBy('module_bundle_id', 'module_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function (object $duplicate): void {
                $keepId = DB::table('module_bundle_items')
                    ->where('module_bundle_id', $duplicate->module_bundle_id)
                    ->where('module_id', $duplicate->module_id)
                    ->min('id');

                DB::table('module_bundle_items')
                    ->where('module_bundle_id', $duplicate->module_bundle_id)
                    ->where('module_id', $duplicate->module_id)
                    ->where('id', '<>', $keepId)
                    ->delete();
            });

        Schema::table('module_bundle_items', function (Blueprint $table): void {
            $table->unique(['module_bundle_id', 'module_id'], self::UNIQUE_NAME);
        });

        Schema::table('module_bundle_items', function (Blueprint $table): void {
            $table->dropForeign(['module_bundle_id']);
            $table->foreign('module_bundle_id')
                ->references('id')
                ->on('module_bundles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('module_bundle_items', function (Blueprint $table): void {
            $table->dropForeign(['module_bundle_id']);
            $table->index('module_bundle_id', self::BUNDLE_INDEX_NAME);
            $table->foreign('module_bundle_id')
                ->references('id')
                ->on('module_bundles');
        });

        Schema::table('module_bundle_items', function (Blueprint $table): void {
            $table->dropUnique(self::UNIQUE_NAME);
        });
    }
};
