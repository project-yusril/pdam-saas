<?php

use App\Support\TenantForeignKeys;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        TenantForeignKeys::assertClean();

        $hasUserUnique = collect(Schema::getIndexes('users'))->contains(
            fn (array $index): bool => $index['columns'] === ['pdam_org_id', 'id'] && $index['unique'],
        );
        if (! $hasUserUnique) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique(['pdam_org_id', 'id'], 'users_org_id_uq');
            });
        }

        foreach (TenantForeignKeys::ACTORS as [$tableName, $column, $foreignName, $indexName]) {
            if (TenantForeignKeys::hasForeignKey($tableName, ['pdam_org_id', $column], 'users')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($column, $foreignName, $indexName): void {
                $table->index(['pdam_org_id', $column], $indexName);
                $table->foreign(['pdam_org_id', $column], $foreignName)
                    ->references(['pdam_org_id', 'id'])->on('users')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        foreach (array_reverse(TenantForeignKeys::ACTORS) as [$tableName, $column, $foreignName, $indexName]) {
            Schema::table($tableName, function (Blueprint $table) use ($foreignName, $indexName): void {
                $table->dropForeign($foreignName);
                $table->dropIndex($indexName);
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_org_id_uq');
        });
    }
};
