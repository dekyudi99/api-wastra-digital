<?php

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderItemPolicy
{
    public function view(User $user, OrderItem $item)
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'artisan') {
            return $item->artisan_id === $user->id;
        }

        if ($user->role === 'customer') {
            return $item->order->customer_id === $user->id;
        }

        return false;
    }

    public function updateStatus(User $user, OrderItem $item)
    {
        // ADMIN selalu boleh
        if ($user->role === 'admin') {
            return true;
        }

        // SELLER: hanya item miliknya
        if ($user->role !== 'artisan') {
            return false;
        }

        if ($item->artisan_id !== $user->id) {
            return false;
        }

        // HARD RULE: tidak boleh ubah setelah shipped
        return in_array($item->item_status, [
            'pending',
            'processing',
        ]);
    }
}
