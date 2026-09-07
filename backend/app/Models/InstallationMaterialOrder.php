<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class InstallationMaterialOrder extends Model
{
    use BelongsToTenant;

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'pdam_org_id',
        'prospect_id',
        'warehouse_id',
        'status',
        'items',
        'total_cost',
        'journal_entry_id',
        'reserved_at',
        'issued_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'total_cost' => 'decimal:2',
            'reserved_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    public function prospect()
    {
        return $this->belongsTo(CustomerProspect::class, 'prospect_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
