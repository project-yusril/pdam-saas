<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/**
 * pdam:mobile-coverage — laporan integritas kontrak mobile ↔ backend (H-10).
 *
 * Membaca konstanta endpoint pada mobile/lib/core/network/endpoints.dart dan
 * membandingkannya dengan route registry riil — placeholder `{id}` vs
 * `{prospect}` dinormalisasi agar tidak false-positive.
 *
 * Output: daftar konstanta mobile yang TIDAK ada pasangan registry (break)
 * + exit code non-zero bila ada.
 *
 *   php artisan pdam:mobile-coverage
 */
class MobileCoverage extends Command
{
    protected $signature = 'pdam:mobile-coverage
                            {--source= : override path berkas endpoints.dart (default: mobile/lib) untuk test/smoke lain}
                            {--report : tampilkan pula daftar route API yang TIDAK dirujuk mobile (gap info)}';

    protected $description = 'Validasi coverage endpoint mobile (endpoints.dart) vs route registry';

    private const HTTP = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(): int
    {
        $dart = ($src = $this->option('source'))
            ? (string) $src
            : base_path('../mobile/lib/core/network/endpoints.dart');
        if (! is_file($dart)) {
            $this->error('endpoints.dart tidak ditemukan: '.$dart);

            return self::FAILURE;
        }

        preg_match_all("#'(/[^']*)'#", (string) file_get_contents($dart), $m);
        $client = $this->normalizeList(array_unique($m[1]));

        $registry = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }
            if (array_intersect($route->methods(), self::HTTP) === []) {
                continue;
            }
            $registry[] = $uri;
        }
        $registry = $this->normalizeList(array_unique($registry));

        $broken = [];
        $matchedRegistry = [];
        foreach ($client as $path) {
            if ($this->matched($path, $registry)) {
                foreach ($registry as $r) {
                    if ($this->isSame($path, $r)) {
                        $matchedRegistry[$r] = true;
                    }
                }

                continue;
            }
            $broken[] = $path;
        }

        foreach ($broken as $b) {
            $this->error('TANPA PASANGAN: '.$b);
        }

        $this->info(sprintf(
            'OK — %d path unik di endpoints.dart cocok dengan %d API route registry (placeholder normal).',
            count($client),
            count($registry),
        ));

        if ($this->option('report')) {
            $unused = array_values(array_diff($registry, array_keys($matchedRegistry)));
            sort($unused);
            $this->line('');
            $this->line(sprintf('GAP (route API tidak dirujuk mobile %d/%d):', count($unused), count($registry)));
            foreach ($unused as $u) {
                $this->line('  '.$u);
            }
            $this->line('');
            $this->line('Catatan: banyak route web/report memang tidak untuk aplikasi lapangan mobile; gap ini untuk tinjauan, bukan kegagalan.');
        }

        $this->line('Catatan utama: ini cek eksistensi path (bukan verifikasi method + skema per-endpoint).');

        return $broken === [] ? self::SUCCESS : self::FAILURE;
    }

    private function isSame(string $path, string $registryPath): bool
    {
        $needle = 'api/v1'.$path;
        $rx = '#^'.preg_replace('#\{p\}#', '[^/]+', preg_quote($registryPath, '#')).'$#';

        return (bool) preg_match($rx, $needle);
    }

    private function normalizeList(array $paths): array
    {
        return array_map(fn ($p) => $this->normalize($p), $paths);
    }

    private function normalize(string $path): string
    {
        $path = preg_replace('/\$\{[^}]+\}/', '{p}', $path);
        $path = preg_replace('/\{[^}?]+\?\}/', '({p})?', $path);
        $path = preg_replace('/\{[^}]+\}/', '{p}', $path);

        return $path;
    }

    private function matched(string $path, array $registry): bool
    {
        $needle = 'api/v1'.$path;
        foreach ($registry as $candidate) {
            if ($needle === $candidate) {
                return true;
            }
            $rx = '#^'.str_replace('({p})?', '(?:/[^/]+)?', preg_replace('#\{p\}#', '[^/]+', preg_quote($candidate, '#'))).'$#';
            if (preg_match($rx, $needle)) {
                return true;
            }
        }

        return false;
    }
}
