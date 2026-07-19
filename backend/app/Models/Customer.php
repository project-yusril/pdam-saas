<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Customer — sambungan air aktif. Fase 1.4 */
class Customer extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'user_id', 'prospect_id', 'customer_number', 'full_name',
        'phone', 'email', 'zone_id', 'street_id', 'address_detail',
        'latitude', 'longitude', 'tariff_category_id', 'meter_serial_number',
        'meter_route_id', 'installation_date', 'initial_reading', 'status',
    ];

    protected function casts(): array
    {
        return [
            'installation_date' => 'date',
            'initial_reading' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function tariffCategory(): BelongsTo
    {
        return $this->belongsTo(TariffCategory::class, 'tariff_category_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(CustomerStatusHistory::class);
    }

    /** Ubah status + catat riwayat. */
    public function changeStatus(string $to, ?string $reason = null, ?int $by = null): void
    {
        $from = $this->status;
        $this->update(['status' => $to]);
        $this->statusHistory()->create([
            'pdam_org_id' => $this->pdam_org_id,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'changed_by' => $by,
        ]);
    }
}
