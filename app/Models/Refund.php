<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Order;
use App\Models\CancelRequest;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'cancel_request_id',
        'order_item_id',
        'midtrans_refund_key',
        'amount',
        'status',
        'response',
    ];

    public function order() {
        return $this->belongsTo(Order::class, 'order_id', 'íd');
    }

    public function cancel_request() {
        return $this->belongsTo(CancelRequest::class, 'cancel_request_id', 'id');
    }
}
