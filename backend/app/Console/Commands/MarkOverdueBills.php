<?php

namespace App\Console\Commands;

use App\Models\Bill;
use Illuminate\Console\Command;

/**
 * MarkOverdueBills — cron tgl 26 jam 06.00 (PRD 10.5).
 * Tandai tagihan unpaid yang melewati jatuh tempo menjadi overdue.
 * Berjalan lintas tenant.
 */
class MarkOverdueBills extends Command
{
    protected $signature = 'pdam:mark-overdue';

    protected $description = 'Ubah tagihan yang melewati jatuh tempo menjadi overdue';

    public function handle(): int
    {
        $today = now()->toDateString();

        $updated = Bill::query()
            ->withoutGlobalScope('tenant')
            ->where('status', 'unpaid')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->update(['status' => 'overdue']);

        $this->info("{$updated} tagihan ditandai overdue.");

        return self::SUCCESS;
    }
}
