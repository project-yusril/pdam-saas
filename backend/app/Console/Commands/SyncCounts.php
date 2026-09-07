<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/**
 * pdam:counts — bukti auto-sync angka inventaris (temuan2.md P3):
 * endpoint (route registry riil), migration, seeder, model, tabel statis
 * (parse Schema::create), test per komponen. Menulis ../docs/COUNTS.json.
 *
 *   php artisan pdam:counts           # laporkan + cocokan vs file committed
 *   php artisan pdam:counts --write   # regenerate ../docs/COUNTS.json
 *
 * CI gate: `--check` = default behavior, gagal bila file committed drift
 * dari hitungan aktual → angka dokumentasi tidak pernah basi.
 */
class SyncCounts extends Command
{
    protected $signature = 'pdam:counts {--write} {--format= : json|table}';

    protected $description = 'Hitung & sinkronkan jumlah endpoint/test/model/tabel/seeder ke docs/COUNTS.json';

    public function handle(): int
    {
        $counts = $this->collect();
        $out = $this->targetPath();

        if ($this->option('format') === 'json') {
            $this->line((string) json_encode($counts, JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($this->option('write')) {
            $dir = dirname($out);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($out, json_encode($counts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
            $this->info('Ditulis: '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $out));

            return self::SUCCESS;
        }

        if (! is_file($out)) {
            $this->error('COUNTS.json belum ada — jalankan `php artisan pdam:counts --write`.');

            return self::FAILURE;
        }

        $committed = json_decode((string) file_get_contents($out), true) ?: [];
        $drift = [];
        foreach ($counts as $key => $value) {
            if ($key === 'generated_at') {
                continue;
            }
            if (($committed[$key] ?? null) !== $value) {
                $drift[] = sprintf('%s: committed=%s aktual=%s', $key, json_encode($committed[$key] ?? null), json_encode($value));
            }
        }

        if ($drift === []) {
            $this->info('COUNTS.json sinkron dengan kondisi repositori ('.json_encode($counts['api_endpoints']).' endpoint API).');

            return self::SUCCESS;
        }

        $this->error('Drift inventaris terdeteksi:');
        foreach ($drift as $line) {
            $this->line('  '.$line);
        }
        $this->line('Regenerate: php artisan pdam:counts --write');

        return self::FAILURE;
    }

    /** @return array<string,int> */
    private function collect(): array
    {
        $api = 0;
        $web = 0;
        foreach (Route::getRoutes() as $route) {
            $methods = array_diff($route->methods(), ['HEAD']);
            if ($methods === []) {
                continue;
            }
            $n = count($methods);
            if (str_starts_with($route->uri(), 'api/')) {
                $api += $n;
            } else {
                $web += $n;
            }
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'api_endpoints' => $api,
            'web_endpoints' => $web,
            'migrations' => count(glob(base_path('database/migrations/*.php'))),
            'seeder_classes' => count(glob(base_path('database/seeders/*.php'))),
            'seeder_production_path' => count(['ProductionKernelSeeder', 'PermissionSeeder', 'ModuleSeeder', 'RoleTemplateSeeder']),
            'models' => count(glob(base_path('app/Models/*.php'))),
            'tables_static' => $this->countTables(),
            'backend_test_methods' => $this->countRegex(base_path('tests'), '/public function (test_|__)/'),
            'frontend_test_files' => $this->countFiles(base_path('resources/js'), ['spec.js', 'test.js']),
            'console_commands' => count(glob(base_path('app/Console/Commands/*.php'))),
        ];
    }

    /** Unik nama tabel yang dibuat lewat Schema::create pada migration. */
    private function countTables(): int
    {
        $tables = [];
        foreach (glob(base_path('database/migrations/*.php')) as $file) {
            if (preg_match_all("/Schema::create\(\s*'([a-z0-9_]+)'/i", (string) file_get_contents($file), $m)) {
                foreach ($m[1] as $t) {
                    $tables[$t] = true;
                }
            }
        }

        // Tabel framework yang dibuat migrasi bawaan ikut terhitung via regex;
        // tidak ada hardcode.
        return count($tables);
    }

    /** Hitung file dengan akhiran nama tertentu secara rekursif. */
    private function countFiles(string $dir, array $suffixes): int
    {
        $n = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if (! $file->isFile()) {
                continue;
            }
            foreach ($suffixes as $sfx) {
                if (str_ends_with($file->getFilename(), $sfx)) {
                    $n++;

                    break;
                }
            }
        }

        return $n;
    }

    private function countRegex(string $dir, string $pattern): int
    {
        $count = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $count += preg_match_all($pattern, (string) file_get_contents($file->getPathname()));
            }
        }

        return $count;
    }

    private function targetPath(): string
    {
        return dirname(base_path()).DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'COUNTS.json';
    }
}
