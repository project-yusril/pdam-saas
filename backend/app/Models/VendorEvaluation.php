<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class VendorEvaluation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'vendor_id', 'contract_id',
        'quality_score', 'delivery_score', 'price_score', 'compliance_score',
        'overall_score', 'comments',
    ];

    protected function casts(): array
    {
        return [
            'quality_score' => 'decimal:1',
            'delivery_score' => 'decimal:1',
            'price_score' => 'decimal:1',
            'compliance_score' => 'decimal:1',
            'overall_score' => 'decimal:1',
        ];
    }
}
