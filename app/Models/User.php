<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Product;
use App\Models\Cart;
use App\Models\Favorit;
use App\Models\Order;
use App\Models\Review;
use App\Models\ShippingAddress;
use App\Models\Conversation;
use App\Models\Message;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'email_verified',
        'password',
        'role',
        'profile_picture',
        'isArtisan',
        'saldo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['profile', 'ktp_url'];

    public function getProfileAttribute()
    {
        if ($this->profile_picture) 
        {
            return (env('APP_URL').'/storage/'.$this->profile_picture);
        }
        return null;
    }

    public function getKtpUrlAttribute()
    {
        if ($this->ktp)
        {
            return (env('APP_URL').'/ktp/'.$this->ktp);
        }
        return null;
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function product() {
        return $this->hasMany(Product::class, 'user_id', 'id');
    }

    public function cart() {
        return $this->hasMany(Cart::class, 'product_id', 'id');
    }

    public function favorit() {
        return $this->hasMany(Favorit::class, 'product_id', 'id');
    }

    public function order() {
        return $this->hasMany(Order::class, 'user_id', 'id');
    }

    public function review() {
        return $this->hasMany(Review::class, 'user_id', 'id');
    }

    public function shipping_address() {
        return $this->hasMany(ShippingAddress::class, 'user_id', 'id');
    }

    public function conversations()
    {
        return $this->belongsToMany(
            Conversation::class,
            'conversation_user',
            'user_id',
            'conversation_id'
        );
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }
}
