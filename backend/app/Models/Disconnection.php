<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Disconnection — pemutusan sambungan (isolir/terminasi). Fase 2.6 */
class Disconnection extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'customer_id', 'type', 'reason', 'effective_date', 'requested_by',
    ];

    protected function casts(): array
    {
        return ['effective_date' => 'date'];
    }
}
