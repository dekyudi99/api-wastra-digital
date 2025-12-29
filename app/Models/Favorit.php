<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use App\Models\User;

class Favorit extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
    ];

    protected function product() {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    protected function user() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
