<?php

use App\Support\TenantForeignKeys;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        TenantForeignKeys::addTenantBatch(0, 25);
    }

    public function down(): void
    {
        TenantForeignKeys::dropTenantBatch(0, 25);
    }
};
