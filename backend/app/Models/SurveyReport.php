<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** SurveyReport — laporan survey lapangan + review Kepala Survey. Fase 2.3/2.4 */
class SurveyReport extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'client_uuid', 'prospect_id', 'surveyor_id', 'photo_house_urls',
        'distance_to_main_pipe', 'building_condition', 'accessibility', 'land_status',
        'latitude', 'longitude', 'location_source', 'location_accuracy',
        'estimated_materials', 'estimated_cost',
        'recommendation', 'surveyor_notes', 'reviewed_by', 'review_status', 'review_notes', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'photo_house_urls' => 'array',
            'estimated_materials' => 'array',
            'distance_to_main_pipe' => 'decimal:2',
            'estimated_cost' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'location_accuracy' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(CustomerProspect::class, 'prospect_id');
    }
}
