<?php

namespace App\Services;

use App\Models\ScheduledReport;
use Carbon\Carbon;

class ScheduledReportService
{
    public function nextRun(array $schedule, ?Carbon $after = null): Carbon
    {
        $after ??= now();
        $local = $after->copy()->setTimezone($schedule['timezone']);
        [$hour, $minute] = array_map('intval', explode(':', $schedule['local_time']));
        $candidate = $local->copy()->setTime($hour, $minute, 0);

        if ($schedule['frequency'] === 'daily') {
            if ($candidate->lte($local)) {
                $candidate->addDay();
            }
        } elseif ($schedule['frequency'] === 'weekly') {
            $candidate->nextOrSame((int) $schedule['day_of_week']);
            if ($candidate->lte($local)) {
                $candidate->addWeek();
            }
        } else {
            $day = min((int) $schedule['day_of_month'], $candidate->daysInMonth);
            $candidate->day($day);
            if ($candidate->lte($local)) {
                $candidate->addMonthNoOverflow()->day(min((int) $schedule['day_of_month'], $candidate->daysInMonth));
            }
        }

        return $candidate->utc();
    }

    public function normalize(array $data): array
    {
        $data['columns'] = array_values($data['columns']);
        $data['filters'] = $data['filters'] ?? [];
        $data['day_of_week'] = $data['frequency'] === 'weekly' ? $data['day_of_week'] : null;
        $data['day_of_month'] = $data['frequency'] === 'monthly' ? $data['day_of_month'] : null;
        $data['next_run_at'] = $this->nextRun($data);

        return $data;
    }

    public function advance(ScheduledReport $report, Carbon $slot): Carbon
    {
        return $this->nextRun($report->toArray(), $slot);
    }
}
