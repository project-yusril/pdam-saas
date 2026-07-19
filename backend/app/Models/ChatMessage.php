<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $fillable = [
        'chat_id', 'sender_id', 'sender_type', 'message', 'attachments', 'is_read',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'json',
            'is_read' => 'boolean',
        ];
    }

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }
}
