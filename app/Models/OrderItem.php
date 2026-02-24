<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Review;
use App\Models\CancelRequest;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'artisan_id',
        'price_at_purchase',
        'name_at_purchase',
        'description_at_purchase',
        'quantity',
        'subtotal',
        'item_status',
        'processing_at',
        'shipped_at',
        'completed_at',
        'finished_at',
        'cancelled_at',
    ];

    public function order() {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function product() {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function seller() {
        return $this->belongsTo(User::class, 'artisan_id', 'id');
    }

    public function review() {
        return $this->hasOne(Review::class, 'order_item_id', 'id');
    }

    public function cancel_request() {
        return $this->hasOne(CancelRequest::class, 'cancel_request_id', 'id');
    }
}
