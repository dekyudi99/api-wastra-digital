<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Product;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Review;
use App\Models\ShippingAddress;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OrderItem;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Models\CancelRequest;
use App\Models\AuditLog;

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
        'role',
        'profile_picture',
        'status',
        'ktp',
        'address',
        'email_verified',
        'email_verified_at',
        'is_delete',
        'password',
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

    public function auditLog() {
        return $this->hasMany(AuditLog::class, 'actor_id', 'íd');
    }

    public function withdrawal() {
        return $this->hasMany(Withdrawal::class, 'seller_id', 'id');
    }

    public function wallet() {
        return $this->hasOne(Wallet::class, 'owner_id', 'id');
    }

    public function product() {
        return $this->hasMany(Product::class, 'artisan_id', 'id');
    }

    public function cart() {
        return $this->hasMany(Cart::class, 'product_id', 'id');
    }

    public function order() {
        return $this->hasMany(Order::class, 'customer_id', 'id');
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

    public function order_item() {
        return $this->hasMany(OrderItem::class, 'user_id', 'id');
    }

    public function cancel_request() {
        return $this->hasMany(CancelRequest::class, 'customer_id', 'id');
    }
}
