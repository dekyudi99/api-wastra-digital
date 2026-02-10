<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\OrderItem;
use App\Models\TransactionHistory;
use App\Models\CancelRequest;
use App\Models\Refund;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'order_code',
        'order_status',
        'payment_status',
        'total_amount',
        'shipping_address',
        'processed_at',
    ];

    public function refund() {
        return $this->hasOne(Refund::class, 'order_id', 'id');
    }

    public function buyer() {
        return $this->belongsTo(User::class, 'customer_id', 'id');
    }

    public function items() {
        return $this->hasMany(OrderItem::class, 'order_id', 'id');
    }

    public function transaction_history() {
        return $this->hasOne(TransactionHistory::class, 'order_id', 'id');
    }

    public function cancel_request() {
        return $this->hasOne(CancelRequest::class, 'order_id', 'id');
    }

    public function refreshStatus()
    {
        if ($this->items()->whereNotIn('item_status', ['completed', 'cancelled'])->exists()) {
            return;
        }

        if ($this->items()->where('item_status', 'completed')->exists()) {
            $this->update(['order_status' => 'completed']);
        } else {
            $this->update(['order_status' => 'cancelled']);
        }
    }
}
