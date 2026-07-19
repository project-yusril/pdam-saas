<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** AccountingPeriod — status buka/tutup periode akuntansi. Fase 1.6 */
class AccountingPeriod extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'period', 'status', 'closed_at', 'closed_by',
    ];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
