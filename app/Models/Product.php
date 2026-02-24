<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\ImagesProduct;
use App\Models\Cart;
use App\Models\OrderItem;
use App\Models\Review;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'description',
        'category',
        'stock',
        'sales',
        'material',
        'wide',
        'long',
        'discount',
        'status',
        'deleted_at',
        'artisan_id',
    ];

    protected $appends = ['last_price', 'review_count', 'rating', 'image_url', ];

    protected $hidden = ['images_product'];

    public function getLastPriceAttribute()
    {
        return (float) $this->price-($this->price * $this->discount / 100);
    }

    public function getReviewCountAttribute() {
        return $this->review()->count();
    }

    public function getRatingAttribute()
    {
        // Menggunakan avg() langsung ke database agar ringan
        // Kita gunakan round() untuk membulatkan, misal 1 angka di belakang koma
        return round($this->review()->avg('rating'), 1) ?? 0;
    }

    public function getImageUrlAttribute()
    {
        return $this->images_product->map(function ($img) {
            return env('APP_URL') . "/storage/" . $img->image; 
        })->toArray();
    }

    public function user() {
        return $this->belongsTo(User::class, 'artisan_id', 'id');
    }

    public function images_product() {
        return $this->hasMany(ImagesProduct::class, 'product_id', 'id');
    }

    public function cart() {
        return $this->hasMany(Cart::class, 'product_id', 'id');
    }

    public function order_item() {
        return $this->hasMany(OrderItem::class, 'product_id', 'id');
    }

    public function review() {
        return $this->hasMany(Review::class, 'product_id', 'id');
    }
}
