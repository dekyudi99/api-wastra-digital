<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Withdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'amount',
        'bank_name',
        'bank_account',
        'status',
    ];

    public function user() {
        return $this->belongsTo(User::class, 'seller_id', 'id');
    }
}
