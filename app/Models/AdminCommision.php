<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminCommision extends Model
{
    use HasFactory;

    protected $fillable = [
        'saldo',
    ];
}
