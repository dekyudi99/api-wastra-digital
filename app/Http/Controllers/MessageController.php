<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conversation;
use App\Models\User;
use App\Http\Resources\ApiResponseDefault;
use Illuminate\Support\Facades\Auth;
use App\Models\Message;
use App\Events\MessageSent;


class MessageController extends Controller
{
    public function index()
    {
        $authId = Auth::id();

        $conversations = Conversation::whereHas('users', function ($q) use ($authId) {
                $q->where('users.id', $authId);
            })
            ->with([
                // ambil user lain (bukan diri sendiri)
                'users' => function ($q) use ($authId) {
                    $q->where('users.id', '!=', $authId)
                    ->select('users.id', 'users.name');
                },
                // ambil pesan terakhir
                'messages' => function ($q) {
                    $q->latest()->limit(1);
                }
            ])
            ->get();
            $data = $conversations->map(function ($conversation) {
            $otherUser = $conversation->users->first();
            $lastMessage = $conversation->messages->first();

            return [
                'id' => $conversation->id,
                'user' => $otherUser ? [
                    'id' => $otherUser->id,
                    'name' => $otherUser->name,
                ] : null,
                'last_message' => $lastMessage?->body,
            ];
        });

        return new ApiResponseDefault(
            true,
            'Berhasil mengambil conversation',
            $data
        );
    }


    public function getOrCreateConversation(int $userId)
    {
        $authId = Auth::id();

        if ($userId === $authId) {
            return new ApiResponseDefault(
                false,
                'Tidak bisa chat dengan diri sendiri',
                null,
                400
            );
        }

        $targetUser = User::find($userId);
        if (!$targetUser) {
            return new ApiResponseDefault(
                false,
                'User tidak ditemukan',
                null,
                404
            );
        }

        $conversation = Conversation::whereHas('users', fn ($q) =>
            $q->where('users.id', $authId)
        )->whereHas('users', fn ($q) =>
            $q->where('users.id', $userId)
        )->first();

        if (!$conversation) {
            $conversation = Conversation::create();
            $conversation->users()->attach([$authId, $userId]);
        }

        return new ApiResponseDefault(
            true,
            'Conversation siap',
            ['conversation_id' => $conversation->id]
        );
    }

    public function getMessages(int $conversationId)
    {
        $allowed = Conversation::where('id', $conversationId)
            ->whereHas('users', fn ($q) =>
                $q->where('users.id', Auth::id())
            )
            ->exists();

        if (!$allowed) {
            return new ApiResponseDefault(
                false,
                'Anda tidak memiliki akses ke chat ini',
                null,
                403
            );
        }

        $messages = Message::where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->get();

        return new ApiResponseDefault(
            true,
            'Pesan berhasil diambil',
            $messages
        );
    }

    public function sendMessage(Request $request, int $conversationId)
    {
        $allowed = Conversation::where('id', $conversationId)
            ->whereHas('users', fn ($q) =>
                $q->where('users.id', Auth::id())
            )
            ->exists();

        if (!$allowed) {
            return new ApiResponseDefault(
                false,
                'Anda tidak memiliki akses ke chat ini',
                null,
                403
            );
        }

        $request->validate([
            'body' => 'required|string'
        ]);

        $message = Message::create([
            'conversation_id' => $conversationId,
            'sender_id'       => Auth::id(),
            'body'            => $request->body
        ]);

        broadcast(new MessageSent($message))->toOthers();

        return new ApiResponseDefault(
            true,
            'Pesan berhasil dikirim',
            $message,
            201
        );
    }
}
