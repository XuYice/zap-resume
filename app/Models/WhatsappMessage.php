<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'user_id',
        'sender_phone',
        'raw_content',
        'is_processed',
        'received_at',
    ];

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}
