<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Topic;

class ChatMessage extends Model
{
    use HasFactory;

    protected $table='chat_message';
    protected $fillable = [
        'topic_id',
        'role',
        'content',
        'type',
        'image_path',
    ];

    public function topic() {
        return $this->belongsTo(Topic::class, 'topic_id', 'id');
    }
}
