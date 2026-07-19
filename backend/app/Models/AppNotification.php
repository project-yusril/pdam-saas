<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** AppNotification — notifikasi in-app/email/push. Fase 1.9 */
class AppNotification extends Model
{
    use BelongsToTenant;

    protected $table = 'app_notifications';

    protected $fillable = [
        'pdam_org_id', 'user_id', 'type', 'title', 'body', 'data', 'channel', 'read_at',
    ];

    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime'];
    }
}
