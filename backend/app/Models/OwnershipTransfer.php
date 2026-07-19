<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** OwnershipTransfer — balik nama pelanggan. NIK dienkripsi. Fase 2.6 */
class OwnershipTransfer extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'customer_id', 'old_owner_name', 'new_owner_name',
        'new_owner_nik', 'new_owner_phone', 'effective_date', 'processed_by',
    ];

    protected $hidden = ['new_owner_nik'];

    protected function casts(): array
    {
        return ['new_owner_nik' => 'encrypted', 'effective_date' => 'date'];
    }
}
