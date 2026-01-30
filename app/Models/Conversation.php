<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ConversationUser;
use App\Models\Message;
use App\Models\User;

class Conversation extends Model
{
    use HasFactory;

    protected $table = 'conversation';
    
    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'conversation_user',
            'conversation_id',
            'user_id'
        );
    }

    public function conversationUser() {
        return $this->hasMany(ConversationUser::class, 'conversation_id', 'id');
    }

    public function messages() {
        return $this->hasMany(Message::class, 'conversation_id', 'id');
    }
}
