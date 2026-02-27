<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\ChatMessage;

class Topic extends Model
{
    use HasFactory;

    protected $table='topic_ai';
    protected $fillable = [
        'user_id',
        'title',
    ];

    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function chat_message() {
        return $this->hasMany(ChatMessage::class, 'topic_id', 'id');
    }
}
