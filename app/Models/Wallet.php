<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\WalletMutation;

class Wallet extends Model
{
    use HasFactory;

    protected $table = 'wallets';
    protected $fillable = [
        'owner_type',
        'owner_id',
        'balance',
    ];

    public function user() {
        return $this->belongsTo(User::class, 'owner_id', 'id');
    }

    public function walletMutation() {
        return $this->hasMany(WalletMutation::class, 'wallet_id', 'id');
    }
}
