<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'doc_number', 'title', 'category', 'version',
        'file_path', 'file_type', 'file_size', 'tags', 'status',
        'uploaded_by', 'retention_until',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'version' => 'integer',
            'file_size' => 'integer',
            'retention_until' => 'date',
        ];
    }
}
