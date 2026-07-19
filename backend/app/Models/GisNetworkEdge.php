<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GisNetworkEdge extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'pipe_feature_id', 'from_node_id', 'from_node_type', 'to_node_id', 'to_node_type', 'length_meters'];

    protected function casts(): array
    {
        return ['length_meters' => 'decimal:2'];
    }

    public function fromFeature(): BelongsTo
    {
        return $this->belongsTo(GisFeature::class, 'from_node_id');
    }

    public function toFeature(): BelongsTo
    {
        return $this->belongsTo(GisFeature::class, 'to_node_id');
    }
}
