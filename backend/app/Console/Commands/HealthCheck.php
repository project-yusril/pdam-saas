<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthCheck extends Command
{
    protected $signature = 'pdam:health-check';

    protected $description = 'Health monitoring untuk semua service';

    public function handle(): int
    {
        $checks = [];

        // MySQL
        try {
            DB::select('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Exception $e) {
            $checks['database'] = 'fail: '.$e->getMessage();
        }

        // Redis
        try {
            Cache::put('health_check', 'ok', 10);
            $val = Cache::get('health_check');
            $checks['redis'] = $val === 'ok' ? 'ok' : 'fail';
        } catch (\Exception $e) {
            $checks['redis'] = 'fail: '.$e->getMessage();
        }

        // Disk
        $free = disk_free_space(storage_path());
        $checks['disk_free_gb'] = round($free / 1024 / 1024 / 1024, 2);

        // Queue
        try {
            $queueSize = DB::table('jobs')->count();
            $checks['pending_jobs'] = $queueSize;
        } catch (\Exception) {
            $checks['pending_jobs'] = 0;
        }

        $allOk = ! collect($checks)->contains(fn ($v) => str_starts_with($v, 'fail'));

        if ($allOk) {
            $this->info('All systems healthy.');
        } else {
            $checks = array_filter($checks, fn ($v) => str_starts_with($v, 'fail'));
            $this->error('Health check fail: '.implode(', ', array_keys($checks)));
        }

        return $allOk ? self::SUCCESS : self::FAILURE;
    }
}
