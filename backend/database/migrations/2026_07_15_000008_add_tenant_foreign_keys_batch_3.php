<?php

use App\Support\TenantForeignKeys;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        TenantForeignKeys::addTenantBatch(50, 25);
    }

    public function down(): void
    {
        TenantForeignKeys::dropTenantBatch(50, 25);
    }
};
