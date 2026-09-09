<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TechnicianLocation — posisi GPS terkini petugas lapangan (upsert per user).
 * Diisi oleh POST /api/v1/field/location (mobile) dan otomatis lewat
 * submit survey / baca meter yang menyertakan koordinat.
 * Tabel tidak memiliki pdam_org_id — tenancy mengikuti user.
 */
class TechnicianLocation extends Model
{
    protected $fillable = ['user_id', 'latitude', 'longitude', 'accuracy'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
