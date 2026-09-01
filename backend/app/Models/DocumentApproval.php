<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DocumentApproval — persetujuan berjenjang dokumen. */
class DocumentApproval extends Model
{
    protected $fillable = ['document_id', 'approver_id', 'approval_order', 'status', 'approved_at', 'comments', 'signature_data'];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'signature_data' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
