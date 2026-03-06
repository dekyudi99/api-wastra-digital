<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WastraKnowledge extends Model
{
    use HasFactory;

    protected $table = 'wastra_knowledge';
    protected $fillable = [
        'nama_wastra',
        'image_path',
        'deskripsi',
        'panduan_sketsa'
    ];
}
