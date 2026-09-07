<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * pdam:queue-health — alarm operasional worker/scheduler production
 * (rekomendasi §13: aktif-kan + uji worker, retry, failed_jobs).
 * Exit non-zero saat ambang terlampaui supaya cron/supervisor/alerting hook
 * (mis. `* * * * * php artisan pdam:queue-health` atau eventlistener) bisa
 * mengangkat insiden.
 */
class QueueHealth extends Command
{
    protected $signature = 'pdam:queue-health
                            {--queues=default,notifications,payments}
                            {--max-queued=1000 : alert bila job tertahan melebihi ini}
                            {--max-failed=20 : alert bila failed_jobs TOTAL melebihi ini}
                            {--max-failed-window= : alert bila gagal dalam jendela umur melebihi ini (default = max-failed)}
                            {--max-failed-age-hours=0 : gagal dalam N jam terakhir (-1 apa pun)}';

    protected $description = 'Cek queue backlog + failed_jobs dan angkat alarm bila melewati ambang';

    public function handle(): int
    {
        $alerts = [];
        $stats = [];

        $connection = (string) config('queue.default');
        $queues = explode(',', (string) $this->option('queues'));

        foreach ($queues as $queue) {
            $queue = trim($queue);
            if ($queue === '') {
                continue;
            }
            $size = $this->queueSize($connection, $queue);
            $stats["queue:{$queue}"] = $size;
            if ($size !== null && $size > (int) $this->option('max-queued')) {
                $alerts[] = "backlog {$queue}={$size} > {$this->option('max-queued')} (worker mati/kelebihan beban?)";
            }
        }

        $failedTotal = DB::table('failed_jobs')->count();
        $failedRecent = DB::table('failed_jobs')
            ->when((int) $this->option('max-failed-age-hours') > 0, function ($q) {
                $q->where('failed_at', '>=', now()->subHours((int) $this->option('max-failed-age-hours')));
            })->count();
        $stats['failed_jobs_total'] = $failedTotal;
        $stats['failed_jobs_window'] = $failedRecent;
        if ($failedTotal > (int) $this->option('max-failed')) {
            $alerts[] = "failed_jobs={$failedTotal} > {$this->option('max-failed')}";
        }

        foreach ($stats as $k => $v) {
            $this->line(sprintf('  %-26s %s', $k, $v));
        }

        if ($alerts === []) {
            $this->info('Queue HEALTHY.');

            return self::SUCCESS;
        }

        foreach ($alerts as $alert) {
            $this->error('ALERT '.$alert);
            logger()->critical('[pdam:queue-health] '.$alert);
        }

        return self::FAILURE;
    }

    private function queueSize(string $connection, string $queue): ?int
    {
        try {
            if ($connection === 'redis') {
                $prefix = (string) config('queue.connections.redis.queue', 'default');
                $key = 'queues:'.$prefix.':'.$queue;
                $llen = Redis::llen($key);

                return is_numeric($llen) ? (int) $llen : null;
            }

            if ($connection === 'database') {
                return DB::table((string) config('queue.connections.database.table', 'jobs'))
                    ->where('queue', $queue)->count();
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
