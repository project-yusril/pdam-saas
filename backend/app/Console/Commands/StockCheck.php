<?php

namespace App\Console\Commands;

use App\Models\MaterialStock;
use App\Models\PdamOrganization;
use App\Services\NotificationChannelService;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class StockCheck extends Command
{
    protected $signature = 'pdam:stock-check';

    protected $description = 'Cek stok di bawah minimum + notif Kepala Gudang';

    public function handle(NotificationChannelService $notif): int
    {
        $organizations = PdamOrganization::all();

        foreach ($organizations as $org) {
            TenantContext::set($org->id);

            $materials = MaterialStock::with(['material', 'warehouse'])
                ->get()
                ->filter(function ($stock) {
                    if (! $stock->material->minimum_stock) {
                        return false;
                    }

                    return $stock->quantity <= $stock->material->minimum_stock;
                });

            foreach ($materials as $stock) {
                $notif->broadcastToRole(
                    'warehouse_head',
                    'Stok Menipis',
                    "Material {$stock->material->name} di {$stock->warehouse->name} tersisa {$stock->quantity} (min: {$stock->material->minimum_stock})."
                );
            }

            TenantContext::clear();
        }

        $this->info('Stock check selesai.');

        return self::SUCCESS;
    }
}
