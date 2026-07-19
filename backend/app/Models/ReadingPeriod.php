<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ReadingPeriod — periode baca meter (buka/tutup). Fase 3.2 */
class ReadingPeriod extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'period', 'status', 'opened_at', 'opened_by', 'closed_at', 'closed_by',
    ];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
