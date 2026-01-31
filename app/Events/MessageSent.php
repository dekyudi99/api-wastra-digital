<?php

namespace App\Events;

// use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Broadcasting\PrivateChannel;
use App\Models\Message;

class MessageSent implements ShouldBroadcastNow
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