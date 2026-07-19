<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class GisFeature extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'feature_type', 'name', 'geometry', 'properties', 'zone_id', 'status'];

    protected function casts(): array
    {
        return ['geometry' => 'json', 'properties' => 'json'];
    }
}
