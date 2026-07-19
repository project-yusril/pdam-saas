<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** AssetMovement — mutasi aset antar lokasi/cabang. Fase 7.4 */
class AssetMovement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'fixed_asset_id', 'from_zone_id', 'to_zone_id',
        'from_location', 'to_location', 'moved_at', 'reason', 'performed_by',
    ];

    protected function casts(): array
    {
        return ['moved_at' => 'date'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }
}
