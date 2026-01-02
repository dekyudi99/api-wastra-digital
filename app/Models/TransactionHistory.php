<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'invoice_number',
        'channel',
        'status',
        'value',
        'email_customer',
    ];
}
