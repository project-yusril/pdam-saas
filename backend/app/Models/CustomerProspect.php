<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** CustomerProspect â€” calon pelanggan. NIK dienkripsi at-rest. Fase 2.1 */
class CustomerProspect extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'pdam_org_id', 'user_id', 'registration_number', 'nik', 'full_name',
        'birth_place', 'birth_date', 'gender', 'religion', 'marital_status',
        'occupation', 'blood_type', 'nationality',
        'address', 'installation_address', 'street_id', 'house_number', 'rt', 'rw', 'zone_id',
        'latitude', 'longitude', 'location_source', 'location_accuracy',
        'email', 'phone', 'ktp_photo_url', 'ktp_file_type', 'ktp_ocr_raw', 'tariff_category_id',
        'status', 'assigned_surveyor_id', 'installation_fee', 'installation_fee_breakdown',
        'payment_due_at', 'rejection_reason',
    ];

    protected $hidden = ['nik'];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',            // PII dilindungi (UU PDP)
            'birth_date' => 'date',
            'ktp_ocr_raw' => 'array',
            'installation_fee' => 'decimal:2',
            'installation_fee_breakdown' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'location_accuracy' => 'decimal:2',
            'payment_due_at' => 'datetime',
        ];
    }

    public function survey(): HasOne
    {
        return $this->hasOne(SurveyReport::class, 'prospect_id')->latestOfMany();
    }

    public function street(): BelongsTo
    {
        return $this->belongsTo(Street::class);
    }
}

