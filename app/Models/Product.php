<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\ImagesProduct;
use App\Models\Cart;
use App\Models\Favorit;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'description',
        'material',
        'wide',
        'long',
        'user_id',
    ];

    protected function user() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    protected function images_product() {
        return $this->hasMany(ImagesProduct::class, 'product_id', 'id');
    }

    protected function cart() {
        return $this->hasMany(Cart::class, 'product_id', 'id');
    }

    protected function favorit() {
        return $this->hasMany(Favorit::class, 'product_id', 'id');
    }
}
