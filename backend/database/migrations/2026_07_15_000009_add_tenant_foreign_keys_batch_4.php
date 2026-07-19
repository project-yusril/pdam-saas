<?php

use App\Support\TenantForeignKeys;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        TenantForeignKeys::addTenantBatch(75, 22);
    }

    public function down(): void
    {
        TenantForeignKeys::dropTenantBatch(75, 22);
    }
};
