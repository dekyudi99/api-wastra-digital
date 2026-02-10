<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Order;

class TransactionHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'order_id',
        'midtrans_order_id',
        'invoice_number',
        'payment_type',
        'status',
        'gross_amount',
        'payload',
        'email_customer',
    ];

    public function order() {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
}
