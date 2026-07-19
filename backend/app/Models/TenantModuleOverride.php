<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantModuleOverride extends Model
{
    protected $fillable = ['pdam_org_id', 'module_code', 'custom_price', 'note', 'set_by', 'valid_until'];

    protected function casts(): array
    {
        return ['custom_price' => 'decimal:2', 'valid_until' => 'date'];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_code', 'code');
    }
}
