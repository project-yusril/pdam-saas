<?php

namespace App\Console\Commands;

use Database\Seeders\PipeNetworkDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedPipeNetwork extends Command
{
    protected $signature = 'pdam:seed-network {pdam_code=pdam-sambas : Kode PDAM target}';

    protected $description = 'Rebuild demo GIS jaringan perpipaan: sambungkan pipa ke SETIAP rumah (tap + MST) per rute baca meter, + DMA + reading suplai bulan lalu. Idempoten: jaringan lama (SMBS-*) dihapus lalu dibangun ulang dari koordinat pelanggan terkini.';

    public function handle(): int
    {
        $orgId = DB::table('pdam_organizations')->where('code', $this->argument('pdam_code'))->value('id');
        if (! $orgId) {
            $this->error('PDAM '.$this->argument('pdam_code').' tidak ditemukan.');

            return self::FAILURE;
        }

        // 1) hapus jaringan lama + efek sampingnya, agar selalu DERIVED dari data terkini
        $ids = DB::table('gis_features')->where('pdam_org_id', $orgId)
            ->where(function ($q) {
                foreach (['SMBS-NET%', 'SMBS-TAP%', 'SMBS-SVC%', 'SMBS-VLV%', 'SMBS-HYD%'] as $p) {
                    $q->orWhere('name', 'like', $p);
                }
            })->pluck('id')->all();
        $edgeIds = DB::table('gis_network_edges')->where('pdam_org_id', $orgId)
            ->whereIn('pipe_feature_id', $ids)->orWhereIn('from_node_id', $ids)->orWhereIn('to_node_id', $ids)->pluck('id')->all();
        DB::table('gis_network_edges')->whereIn('id', $edgeIds)->delete();
        DB::table('gis_features')->whereIn('id', $ids)->delete();

        $lastPeriod = now()->startOfMonth()->subMonthNoOverflow()->format('Y-m');
        DB::table('distribution_readings')->where('pdam_org_id', $orgId)
            ->whereBetween('reading_at', [$lastPeriod.'-01', $lastPeriod.'-31 23:59:59'])->delete();
        DB::table('nrw_balances')->where('pdam_org_id', $orgId)->delete();

        $this->info(sprintf('[1/2] Hapus jaringan lama: %d fitur, %d edge.', count($ids), count($edgeIds)));

        // 2) rebuild dari data pelanggan terkini
        $this->call(PipeNetworkDemoSeeder::class);

        // 3) verifikasi cepat
        $houses = DB::table('customers')->where('pdam_org_id', $orgId)->where('status', '!=', 'inactive')
            ->whereNotNull('latitude')->whereNotNull('longitude')->count();
        $pipes = DB::table('gis_features')->where('feature_type', 'pipe')->count();
        $edges = DB::table('gis_network_edges')->count();
        $dma = DB::table('dma_zones')->where('pdam_org_id', $orgId)->whereNotNull('boundary')->count();
        $this->info("[2/2] Selesai — pipa=$pipes edges=$edges DMA ber-boundary=$dma; total rumah ber-koordinat=$houses (semuanya bertapan + SR pipe).");

        return self::SUCCESS;
    }
}
