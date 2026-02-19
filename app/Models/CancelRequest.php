<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\CancelApproval;
use App\Models\Refund;

class CancelRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_item_id',
        'buyer_id',
        'reason',
        'status',
    ];

    public function refund() {
        return $this->hasOne(Refund::class, 'cancel_request_id', 'id');
    }

    public function order_item() {
        return $this->belongsTo(OrderItem::class, 'order_item_id', 'id');
    }

    public function user() {
        return $this->belongsTo(User::class, 'buyer_id', 'id');
    }

    public function cancel_approval() {
        return $this->hasOne(CancelApproval::class, 'cancel_request_id', 'id');
    }
}
