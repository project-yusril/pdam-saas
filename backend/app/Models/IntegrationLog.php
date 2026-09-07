<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** IntegrationLog — Auto-generated dari skema tabel. */
class IntegrationLog extends Model
{
    use BelongsToTenant;

    protected $fillable = ['pdam_org_id', 'integration_id', 'action', 'status', 'http_status', 'request_payload', 'response_payload', 'error_message', 'retry_count'];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'retry_count' => 'decimal:2',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
