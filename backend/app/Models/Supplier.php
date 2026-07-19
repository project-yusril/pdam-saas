<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Supplier — pemasok material. Fase 4.1 */
class Supplier extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'code', 'name', 'phone', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
