<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** CustomerStatusHistory — jejak perubahan status pelanggan. Fase 1.4 */
class CustomerStatusHistory extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $table = 'customer_status_history';

    protected $fillable = [
        'pdam_org_id', 'customer_id', 'from_status', 'to_status', 'reason', 'changed_by',
    ];
}
