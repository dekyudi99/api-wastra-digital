<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ShippingAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'received_name',
        'telepon_number',
        'provinsi',
        'kabupaten',
        'kecamatan',
        'kode_pos',
        'alamat_detail',
        'user_id',
    ];

    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
