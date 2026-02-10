<?php

namespace App\Policies;

use App\Models\CancelRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use App\Models\OrderItem;

class CancelRequestPolicy
{
    public function create(User $user, OrderItem $item)
    {
        if ($user->role !== 'customer') {
            return false;
        }

        if ($item->order->customer_id !== $user->id) {
            return false;
        }

        // Tidak bisa cancel setelah shipped
        return in_array($item->item_status, [
            'pending',
            'processing',
        ]);
    }

    public function sellerApprove(User $user, CancelRequest $cancel)
    {
        if ($user->role !== 'artisan') {
            return false;
        }

        return $cancel->orderItem->artisan_id === $user->id
            && $cancel->status === 'requested';
    }

    public function adminApprove(User $user, CancelRequest $cancel)
    {
        return $user->role === 'admin'
            && $cancel->status === 'seller_approved';
    }
}
