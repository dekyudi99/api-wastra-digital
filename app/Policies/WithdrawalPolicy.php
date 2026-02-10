<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Auth\Access\Response;

class WithdrawalPolicy
{
    public function create(User $user)
    {
        return $user->role === 'artisan';
    }

    public function approve(User $user, Withdrawal $withdrawal)
    {
        return $user->role === 'admin'
            && $withdrawal->status === 'pending';
    }

    public function markPaid(User $user, Withdrawal $withdrawal)
    {
        return $user->role === 'admin'
            && $withdrawal->status === 'approved';
    }
}
