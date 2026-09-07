<?php

namespace Tests\Feature;

use App\Console\Commands\MobileCoverage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bukti tooling H-10/CI gate command berjalan benar (bukan hanya sukses jalan).
 */
class PdamOpsCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_coverage_command_passes_for_current_endpoints_dart(): void
    {
        $this->artisan(MobileCoverage::class)
            ->expectsOutputToContain('cocok dengan')
            ->assertExitCode(0);
    }

    public function test_mobile_coverage_exits_failure_for_phantom_endpoint_dart(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'edtdart');
        $dart = $tmp.'.dart';
        @unlink($tmp);
        file_put_contents($dart, "class Endpoints {\n  static const String x = '/___phantom___';\n}\n");

        try {
            $this->artisan(MobileCoverage::class, ['--source' => $dart])
                ->expectsOutput('TANPA PASANGAN: /___phantom___')
                ->assertExitCode(1);
        } finally {
            @unlink($dart);
        }
    }

    public function test_open_api_generator_writes_complete_spec_when_path_missing(): void
    {
        $out = base_path('../.phpunit-temp/openapi-check.json');
        if (! is_dir(dirname($out))) {
            mkdir(dirname($out), 0777, true);
        }

        $code = $this->artisan('pdam:openapi', ['--out' => $out])->run();
        $this->assertSame(0, $code);
        $this->assertFileExists($out);

        $spec = json_decode((string) file_get_contents($out), true);
        $this->assertSame('3.0.0', $spec['openapi']);
        $this->assertGreaterThanOrEqual(290, count($spec['paths']), 'spek harus mencakup hampir semua route /api/v1');
        $this->assertArrayHasKey('/login', $spec['paths']);
        $this->assertArrayHasKey('post', $spec['paths']['/login']);

        @unlink($out);
        @rmdir(dirname($out));
    }
}
