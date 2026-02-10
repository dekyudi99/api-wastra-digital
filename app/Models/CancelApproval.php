<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CancelRequest;

class CancelApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'cancel_request_id',
        'role',
        'approved',
        'note',
    ];

    public function cancel_request() {
        return $this->belongsTo(CancelRequest::class, 'cancel_request_id', 'id');
    }
}
