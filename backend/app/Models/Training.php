<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'title', 'start_date', 'end_date', 'provider', 'cost', 'status'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'cost' => 'decimal:2'];
    }
}
