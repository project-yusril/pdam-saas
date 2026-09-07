<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lapisan DB-level append-only untuk privacy_audit_events (SECURITY_CHECKLIST A9
 * + gate least-privilege): penolakan UPDATE/DELETE bahkan bila akun runtime
 * secara keliru masih memegang DELETE/UPDATE. Pada SQLite trigger tidak ada —
 * model guard PHP tetap menegakkan (lihat PrivacyAuditEvent::booted).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            CREATE TRIGGER privacy_audit_events_no_update
            BEFORE UPDATE ON privacy_audit_events
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'privacy_audit_events is append-only (PDAM least-privilege gate)'
        ");

        DB::statement("
            CREATE TRIGGER privacy_audit_events_no_delete
            BEFORE DELETE ON privacy_audit_events
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'privacy_audit_events is append-only (PDAM least-privilege gate)'
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS privacy_audit_events_no_update');
        DB::statement('DROP TRIGGER IF EXISTS privacy_audit_events_no_delete');
    }
};
