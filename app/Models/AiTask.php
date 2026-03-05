<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiTask extends Model
{
    protected $table = 'ai_tasks';
    protected $fillable = [
        'user_id',
        'whatsapp_message_id',
        'description',
        'priority_level',
        'status',
    ];

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function whatsappMessage() {
        return $this->belongsTo(WhatsappMessage::class, 'whatsapp_message_id');
    }
}
