<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MeterReplacement — pencatatan ganti meter (edge case konsumsi). Fase 3.3 */
class MeterReplacement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'customer_id', 'old_serial', 'new_serial',
        'old_final_reading', 'new_initial_reading', 'replaced_at', 'reason', 'processed_by',
    ];

    protected function casts(): array
    {
        return [
            'old_final_reading' => 'integer',
            'new_initial_reading' => 'integer',
            'replaced_at' => 'date',
        ];
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
