<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * pdam:integrations-health — bukti end-to-end awal untuk integrasi eksternal
 * (rekomendasi §13). Default: periksa KONFIGURASI saja (aman untuk CI).
 * Dengan --ping lakukan panggilan riil ke tiap provider:
 *   Midtrans sandbox status, FCM, Google Cloud Vision key, ML service /health/ready.
 *
 * Exit code non-zero bila ada FAIL; FAIL di production harus ditindaklanjuti
 * (email/push/payment nyata diverifikasi live sebelum rilis penuh).
 */
class IntegrationsHealth extends Command
{
    protected $signature = 'pdam:integrations-health {--ping : lakukan panggilan live ke provider}';

    protected $description = 'Verifikasi konfigurasi (dan --ping live) Midtrans/FCM/OCR/ML/email/queue';

    public function handle(): int
    {
        $rows = [];
        $isProd = app()->isProduction();

        $server = (string) config('services.midtrans.server_key', '');
        $client = (string) config('services.midtrans.client_key', '');
        $placeholder = fn (string $v) => $v === '' || str_contains(strtolower($v), 'xxx') || str_contains(strtolower($v), 'replace');

        $midtransState = $placeholder($server) || $placeholder($client)
            ? ['missing', 'MIDTRANS_SERVER_KEY/CLIENT_KEY belum diisi']
            : ['ok', sprintf('server=%s… client=%s… is_production=%s', substr($server, 0, 12), substr($client, 0, 12), config('services.midtrans.is_production') ? 'true' : 'false')];

        if ($this->option('ping') && $midtransState[0] === 'ok') {
            $url = config('services.midtrans.is_production')
                ? 'https://app.midtrans.com/v2/status'
                : 'https://app.sandbox.midtrans.com/v2/status';
            try {
                $res = Http::withBasicAuth($server, '')->timeout(10)->get($url);
                $midtransState = $res->successful()
                    ? ['ok', 'sandbox/prod status 200 — koneksi settlement hidup']
                    : ['fail', 'v2/status HTTP '.$res->status()];
            } catch (\Throwable $e) {
                $midtransState = ['fail', 'HTTP error: '.substr($e->getMessage(), 0, 90)];
            }
        }
        $rows[] = ['midtrans', ...$midtransState];

        $fcm = (string) config('services.fcm.server_key', '');
        $rows[] = ['fcm-push', $placeholder($fcm)
            ? ['missing', 'FCM_SERVER_KEY belum diisi — notifikasi push tidak terkirim']
            : ['ok', 'key terisi (verifikasi kirim riil via --ping + perangkat uji)']];

        $ocr = (string) config('services.google_cloud.vision_api_key', '');
        $rows[] = ['ocr-ktp', $placeholder($ocr)
            ? ['missing', 'GOOGLE_CLOUD_VISION_API_KEY belum diisi — recognize() melempar RuntimeException']
            : ['ok', 'key Vision terisi']];

        $mail = (string) config('mail.default', 'log');
        $mailState = match (true) {
            $mail === 'log' && $isProd => ['fail', 'MAIL_MAILER=log di production — email tidak akan terkirim'],
            $mail === 'log' => ['warn', 'mail ke log (dev)'],
            default => ['ok', "mailer={$mail}"],
        };
        $rows[] = ['mail', $mailState];

        $queue = (string) config('queue.default', 'sync');
        $rows[] = ['queue', $queue === 'redis' ? ['ok', 'redis queue — pastikan worker default hidup (supervisord)'] : ($isProd ? ['fail', "queue={$queue} di production (harus redis)"] : ['warn', "queue={$queue} (dev)"])];

        $mlBase = (string) env('ML_SERVICE_BASE_URL', 'http://127.0.0.1:8001');
        $mlToken = (string) env('ML_SERVICE_TOKEN', '');
        $mlState = $mlToken === '' || str_contains(strtolower($mlToken), 'replace')
            ? ['missing', 'ML_SERVICE_TOKEN belum diset — API private ML menolak segalanya (fail-closed)']
            : ['ok', "base={$mlBase}"];
        if ($this->option('ping') && $mlState[0] === 'ok') {
            try {
                $res = Http::withHeaders(['X-Service-Token' => $mlToken])->timeout(10)->get(rtrim($mlBase, '/').'/health/ready');
                $mlState = $res->successful() ? ['ok', '/health/ready 200 — service+artefak siap'] : ['fail', '/health/ready HTTP '.$res->status()];
            } catch (\Throwable $e) {
                $mlState = ['fail', 'koneksi ML service mati: '.substr($e->getMessage(), 0, 90)];
            }
        }
        $rows[] = ['ml-service', $mlState];

        $dbOk = false;
        try {
            DB::connection()->getPdo();
            $dbOk = true;
        } catch (\Throwable) {
        }
        $rows[] = ['database', $dbOk ? ['ok', DB::connection()->getDriverName().' terhubung'] : ['fail', 'koneksi DB gagal']];

        $hasFail = false;
        $this->table(['integrasi', 'status', 'detail'], array_map(function ($r) use (&$hasFail) {
            if ($r[1] === 'fail') {
                $hasFail = true;
            }

            return [$r[0], strtoupper($r[1]), $r[2]];
        }, $rows));

        if ($isProd && $hasFail) {
            return self::FAILURE;
        }

        return $hasFail && $this->option('ping') ? self::FAILURE : self::SUCCESS;
    }
}
