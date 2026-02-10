<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\OrderItem;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_item_id',
        'comment',
        'rating',
    ];

    protected $appends = ['reviewer'];

    protected $hidden = ['user'];

    public function getReviewerAttribute() {
        return $this->user;
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function order_item() {
        return $this->belongsTo(OrderItem::class, 'order_item_id', 'id');
    }
}
