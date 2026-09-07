<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * pdam:audit-db-privileges — bukti operasional gate least-privilege
 * (temuan2.md §13 #4 + SECURITY_CHECKLIST A9). Pemeriksaan read-only
 * terhadap hak akses akun yang sebenarnya dipakai koneksi runtime/audit:
 *  - runtime  : bebas DDL (CREATE/ALTER/DROP/INDEX/ROUTINE/EVENT).
 *  - audit    : bila DB_AUDIT_USERNAME diset (connection `audit` khusus
 *               privacy_audit_events) → hanya INSERT+SELECT pada tabel itu.
 *  - trigger  : privacy_audit_events_no_update/no_delete ADA pada MySQL,
 *               menutup lapisan mutasi bahkan bila grant menyimpang.
 *
 * Non-MySQL (sqlite dev/test) tidak punya grant/trigger layer → SKIP exit 0;
 * di production command ini WAJIB exit 0 sebagai evidence gate.
 */
class AuditDbPrivileges extends Command
{
    protected $signature = 'pdam:audit-db-privileges';

    protected $description = 'Audit least-privilege DB production (app/migrate/backup/audit + append-only privacy_audit_events)';

    private const FORBIDDEN_RUNTIME = ['CREATE', 'ALTER', 'DROP', 'INDEX', 'CREATE ROUTINE', 'ALTER ROUTINE', 'EVENT', 'GRANT OPTION'];

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->warn('Driver '.DB::connection()->getDriverName().' — pemeriksaan privilege hanya bermakna pada MySQL production (SKIP).');

            return self::SUCCESS;
        }

        $failures = [];
        $notes = [];

        $grants = $this->grantsFor((string) config('database.default'));
        if ($grants === null) {
            $this->error('FAIL  SHOW GRANTS tidak dapat diambil untuk runtime — DB tidak terjangkau/credential salah.');

            return self::FAILURE;
        }
        $global = $this->effective($this->linePrivileges($grants, fn ($on) => str_contains($on, '*')));

        $ddlHeld = array_values(array_filter(
            self::FORBIDDEN_RUNTIME,
            fn ($p) => in_array($p, $global, true),
        ));
        if ($ddlHeld !== []) {
            $failures[] = 'Akun runtime memegang DDL/admin: '.implode(', ', $ddlHeld).' (hanya akun migration yang boleh).';
        } else {
            $notes[] = 'runtime: tanpa DDL/admin scope database ✓';
        }

        $auditTablePrivs = $this->effective($this->tablePrivileges($grants, 'privacy_audit_events'));
        $mutateViaGrant = $this->canMutateAuditTable($global, $auditTablePrivs);
        $triggersActive = $this->triggersPresent();

        if ($triggersActive) {
            $notes[] = 'trigger append-only privacy_audit_events AKTIF (no_update + no_delete) ✓';
        }

        if ($mutateViaGrant && ! $triggersActive) {
            $failures[] = 'privacy_audit_events masih dapat dimutasi akun runtime TANPA trigger — terapkan database/provisioning/mysql-privileges.sql + migration trigger.';
        } elseif (! $mutateViaGrant) {
            $notes[] = 'runtime: tidak memegang UPDATE/DELETE privacy_audit_events dari grant ✓';
        } elseif ($mutateViaGrant && $triggersActive) {
            $notes[] = 'runtime memegang UPDATE/DELETE db-level; trigger menutup mutasi — pertimbangkan akun pdam_audit terpisah (DB_AUDIT_*).';
        }

        if (filled(env('DB_AUDIT_USERNAME'))) {
            $auditGrants = $this->grantsFor('audit');
            if ($auditGrants === null) {
                $failures[] = 'koneksi `audit` (DB_AUDIT_*) tidak terjangkau — gate least-privilege belum bisa dibuktikan.';
                $auditGrants = [];
            }
            $violations = [];
            foreach ($auditGrants as $grant) {
                $line = strtoupper($grant);
                if (! preg_match('/GRANT (.+?) ON (.+?)( TO |$)/', $line, $m)) {
                    continue;
                }
                [$privRaw, $on] = [$m[1], trim($m[2])];
                $privs = array_map(fn ($p) => trim(str_replace(' PRIVILEGES', '', $p)), explode(',', $privRaw));

                if ($privs === ['USAGE'] || $privs === ['USAGE PRIVILEGES']) {
                    continue; // bawaan CREATE USER, bukan privilege efektif
                }
                $tableScoped = str_contains($on, 'PRIVACY_AUDIT_EVENTS');

                if (in_array('ALL PRIVILEGES', $privs, true) || ! $tableScoped) {
                    $violations[] = $line;

                    continue;
                }
                foreach ($privs as $p) {
                    if (! in_array($p, ['INSERT', 'SELECT'], true)) {
                        $violations[] = "{$line} (privilege {$p} di luar INSERT/SELECT)";
                    }
                }
            }
            if ($violations === []) {
                $notes[] = 'akun audit (connection `audit`): hanya INSERT/SELECT pada privacy_audit_events ✓';
            } else {
                $failures[] = 'akun audit melebihi INSERT/SELECT: '.implode(' | ', $violations);
            }
        } else {
            $notes[] = 'DB_AUDIT_* tidak diset (dev) — production WAJIB pakai akun pdam_audit terpisah.';
        }

        foreach ($notes as $note) {
            $this->info('PASS  '.$note);
        }
        foreach ($failures as $failure) {
            $this->error('FAIL  '.$failure);
        }

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<int,string>|null baris SHOW GRANTS, null bila gagal */
    private function grantsFor(string $connection): ?array
    {
        try {
            return array_map(
                fn ($row) => (string) reset($row),
                DB::connection($connection)->select('SHOW GRANTS FOR CURRENT_USER()'),
            );
        } catch (\Throwable $e) {
            $this->error('FAIL  SHOW GRANTS gagal pada connection '.$connection.': '.$e->getMessage());

            return null;
        }
    }

    /** @return array<int,string> */
    private function auditGrants(): array
    {
        return $this->grantsFor('audit') ?? [];
    }

    /**
     * Kembangkan `ALL`/`ALL PRIVILEGES` menjadi semua privilege konkret supaya
     * pemeriksaan DDL & mutasi tidak fail-OPEN saat akun runtime keliru granted ALL.
     *
     * @param  array<int,string>  $privs
     * @return array<int,string>
     */
    private function effective(array $privs): array
    {
        if (in_array('ALL', $privs, true) || in_array('ALL PRIVILEGES', $privs, true)) {
            return array_values(array_unique(array_merge(
                $privs,
                ['CREATE', 'ALTER', 'DROP', 'INDEX', 'CREATE ROUTINE', 'ALTER ROUTINE',
                    'EVENT', 'GRANT OPTION', 'INSERT', 'SELECT', 'UPDATE', 'DELETE', 'TRUNCATE'],
            )));
        }

        return $privs;
    }

    /**
     * Privilege yang diberikan lewat baris GRANT ... ON <scope match>.
     *
     * @param  callable(string $onClause): bool  $scopeMatch
     * @return array<int,string>
     */
    private function linePrivileges(array $grants, callable $scopeMatch): array
    {
        $privs = [];
        foreach ($grants as $line) {
            $line = strtoupper($line);
            if (! preg_match('/GRANT (.+?) ON (.+?)( TO |$)/', $line, $m)) {
                continue;
            }
            if (! $scopeMatch(trim($m[2]))) {
                continue;
            }
            foreach (explode(',', $m[1]) as $p) {
                $privs[] = str_replace(' PRIVILEGES', '', trim($p));
            }
        }

        return $privs;
    }

    /** @return array<int,string> */
    private function tablePrivileges(array $grants, string $table): array
    {
        $found = [];
        foreach ($grants as $line) {
            if (preg_match('/^GRANT (.+?) ON `?\w+`?\.`?'.preg_quote($table, '`').'`?/i', strtoupper($line), $m)) {
                foreach (explode(',', $m[1]) as $p) {
                    $found[] = str_replace(' PRIVILEGES', '', trim($p));
                }
            }
        }

        return $found;
    }

    /** UPDATE/DELETE pada privacy_audit_events efektif via grant global/db-level/tabel-level? */
    private function canMutateAuditTable(array $globalPrivs, array $tablePrivs): bool
    {
        foreach (['UPDATE', 'DELETE', 'ALL PRIVILEGES'] as $p) {
            if (in_array($p, $globalPrivs, true) || in_array($p, $tablePrivs, true)) {
                return true;
            }
        }

        return false;
    }

    private function triggersPresent(): bool
    {
        try {
            $rows = DB::select(
                'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE EVENT_OBJECT_TABLE = ? AND TRIGGER_SCHEMA = DATABASE()',
                ['privacy_audit_events'],
            );
            $names = array_map(fn ($r) => strtolower((string) $r->TRIGGER_NAME), (array) $rows);
            if (in_array('privacy_audit_events_no_update', $names, true) && in_array('privacy_audit_events_no_delete', $names, true)) {
                return true;
            }

            // MySQL menyembunyikan information_schema.TRIGGERS dari user tanpa TRIGGER
            // privilege (runtime akun `pdam_app` memang tidak punya, by design) → fallback:
            // apakah migration pembuat trigger tercatat jalan.
            return DB::table('migrations')->where('migration', '2026_09_06_000002_add_privacy_audit_append_only_triggers')->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
