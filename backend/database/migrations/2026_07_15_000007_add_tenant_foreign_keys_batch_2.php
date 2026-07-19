<?php

use App\Support\TenantForeignKeys;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        TenantForeignKeys::addTenantBatch(25, 25);
    }

    public function down(): void
    {
        TenantForeignKeys::dropTenantBatch(25, 25);
    }
};
