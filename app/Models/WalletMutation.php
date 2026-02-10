<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Wallet;

class WalletMutation extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'type',
        'amount',
        'source',
        'ref_id'
    ];

    public function wallet() {
        return $this->belongsTo(Wallet::class, 'wallet_id', 'id');
    }
}
