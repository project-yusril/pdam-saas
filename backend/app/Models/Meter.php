<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Meter — master meter fisik PDAM (lifecycle gudang→terpasang→afkir). Fase 6.1 */
class Meter extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'serial_number', 'brand', 'model', 'diameter',
        'install_year', 'install_date', 'condition', 'location_note',
        'last_calibration_date', 'status', 'tamper_status', 'warranty_until', 'customer_id',
    ];

    protected function casts(): array
    {
        return [
            'install_year' => 'integer',
            'install_date' => 'date',
            'last_calibration_date' => 'date',
            'warranty_until' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MeterLifecycleEvent::class);
    }

    public function anomalies(): HasMany
    {
        return $this->hasMany(MeterAnomaly::class);
    }

    public function stockEntries(): HasMany
    {
        return $this->hasMany(MeterStock::class);
    }

    /** Umur meter dalam tahun (dari install_date atau install_year). */
    public function ageInYears(): ?int
    {
        if ($this->install_date) {
            return $this->install_date->diffInYears(now());
        }
        if ($this->install_year) {
            return (int) now()->year - $this->install_year;
        }

        return null;
    }
}
