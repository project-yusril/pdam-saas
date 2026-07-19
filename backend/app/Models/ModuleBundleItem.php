<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleBundleItem extends Model
{
    protected $fillable = ['module_bundle_id', 'module_id'];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(ModuleBundle::class, 'module_bundle_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
