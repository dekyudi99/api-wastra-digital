<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Product;

class ImagesProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'image',
        'product_id',
    ];

    protected function product() {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
