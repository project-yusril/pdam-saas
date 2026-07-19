<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meter_readings', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->after('pdam_org_id');
            $table->unique(['pdam_org_id', 'client_uuid']);
        });

        Schema::table('survey_reports', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->after('pdam_org_id');
            $table->unique(['pdam_org_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('survey_reports', function (Blueprint $table) {
            $table->dropUnique(['pdam_org_id', 'client_uuid']);
            $table->dropColumn('client_uuid');
        });
        Schema::table('meter_readings', function (Blueprint $table) {
            $table->dropUnique(['pdam_org_id', 'client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
