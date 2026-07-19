<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pdam_org_id', 'customer_id', 'user_id', 'assigned_to', 'subject', 'status',
    ];

    public function lastMessage()
    {
        return $this->hasOne(ChatMessage::class)->latest();
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
