<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MeterLifecycleEvent — riwayat perubahan status/lifecycle meter. Fase 6.1 */
class MeterLifecycleEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'meter_id', 'event', 'from_status', 'to_status',
        'customer_id', 'note', 'performed_by',
    ];

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
