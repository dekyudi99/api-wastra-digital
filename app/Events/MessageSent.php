<?php

namespace App\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Broadcasting\PrivateChannel;
use App\Models\Message;

class MessageSent implements ShouldBroadcast
{
    public function __construct(public Message $message) {}

    public function broadcastOn()
    {
        return new PrivateChannel(
            'chat.' . $this->message->conversation_id
        );
    }

    public function broadcastAs()
    {
        return 'message.sent';
    }
}