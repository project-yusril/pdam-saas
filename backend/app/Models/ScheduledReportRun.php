<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ScheduledReportRun extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'scheduled_report_id', 'scheduled_for', 'status', 'artifact_path', 'filename', 'content_type', 'row_count', 'error', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['scheduled_for' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
