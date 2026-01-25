<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\ImagesProduct;
use App\Models\Cart;
use App\Models\Favorit;
use App\Models\OrderItem;
use App\Models\Review;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'price',
        'description',
        'category',
        'stock',
        'material',
        'wide',
        'long',
        'discount',
    ];

    protected $appends = ['review_count', 'rating', 'last_price', 'image_url'];

    protected $hidden = ['images_product'];

    public function getLastPriceAttribute()
    {
        return (float) $this->price-($this->price * $this->discount / 100);
    }

    public function getImageUrlAttribute()
    {
        return $this->images_product->map(function ($img) {
            return env('APP_URL') . "/storage/" . $img->image; 
        })->toArray();
    }

    public function getReviewCountAttribute()
    {
        return $this->review_count ?? $this->review()->count();
    }

    public function getRatingAttribute() {
        return round($this->review()->avg('rating') ?? 0, 1);
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function images_product() {
        return $this->hasMany(ImagesProduct::class, 'product_id', 'id');
    }

    public function cart() {
        return $this->hasMany(Cart::class, 'product_id', 'id');
    }

    public function favorit() {
        return $this->hasMany(Favorit::class, 'product_id', 'id');
    }

    public function order_item() {
        return $this->hasMany(OrderItem::class, 'product_id', 'id');
    }

    public function review() {
        return $this->hasMany(Review::class, 'product_id', 'id');
    }
}
