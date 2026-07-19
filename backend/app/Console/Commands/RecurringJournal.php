<?php

namespace App\Console\Commands;

use App\Models\PdamOrganization;
use App\Models\RecurringTransaction;
use App\Services\JournalService;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class RecurringJournal extends Command
{
    protected $signature = 'pdam:recurring-journal';

    protected $description = 'Jalankan recurring journal yang jatuh tempo hari ini';

    public function handle(JournalService $journal): int
    {
        $organizations = PdamOrganization::all();
        $today = now()->toDateString();

        foreach ($organizations as $org) {
            TenantContext::set($org->id);

            $due = RecurringTransaction::where('is_active', true)
                ->whereDate('next_run_date', '<=', $today)
                ->get();

            foreach ($due as $rt) {
                $lines = json_decode($rt->journal_lines, true);
                $entry = $journal->record(
                    $rt->name,
                    $lines,
                    'recurring',
                    $rt->id,
                );

                $rt->update([
                    'last_entry_id' => $entry->id,
                    'next_run_date' => $this->nextDate($rt->frequency, $rt->next_run_date),
                ]);

                $this->info("Recurring: {$rt->name} posted.");
            }

            TenantContext::clear();
        }

        return self::SUCCESS;
    }

    private function nextDate(string $frequency, string $from): string
    {
        return match ($frequency) {
            'daily' => now()->parse($from)->addDay()->toDateString(),
            'weekly' => now()->parse($from)->addWeek()->toDateString(),
            'monthly' => now()->parse($from)->addMonth()->toDateString(),
            'quarterly' => now()->parse($from)->addMonths(3)->toDateString(),
            'annual' => now()->parse($from)->addYear()->toDateString(),
            default => now()->parse($from)->addMonth()->toDateString(),
        };
    }
}
