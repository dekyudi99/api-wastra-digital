<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenunGuide extends Model
{
    protected $fillable = [
        'user_id',
        'design_name',
        'motif_width_lungsin',
        'motif_height_pakan',
        'motif_colors',
        'reference_image_path',
        'ai_result'
    ];

    protected $casts = [
        'motif_colors' => 'array',
        'ai_result' => 'array'
    ];
}
