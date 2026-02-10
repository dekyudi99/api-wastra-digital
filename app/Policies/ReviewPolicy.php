<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use App\Models\OrderItem;

class ReviewPolicy
{
    public function create(User $user, OrderItem $item)
    {
        if ($user->role !== 'customer') {
            return false;
        }

        if ($item->order->customer_id !== $user->id) {
            return false;
        }

        return $item->item_status === 'completed';
    }
}
